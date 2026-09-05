import type { RealtimeConfig } from '@castleroyale/contracts';

/**
 * The minimal surface `subscribeToCityChannel` needs from a socket. Tests
 * drive a fake implementing this shape so no real `WebSocket` ever opens.
 */
export type SocketLike = {
  send: (data: string) => void;
  close: () => void;
  onopen: (() => void) | null;
  onmessage: ((event: { data: string }) => void) | null;
  onerror: ((error: unknown) => void) | null;
  onclose: (() => void) | null;
};

export type SocketFactory = (url: string) => SocketLike;

export type CityChannelOptions = {
  config: RealtimeConfig;
  cityId: string;
  onEvent: (event: string) => void;
  getAccessToken: () => Promise<string | undefined>;
  socketFactory?: SocketFactory | undefined;
  fetchImpl?: typeof fetch | undefined;
};

export function cityChannelName(cityId: string): string {
  return `private-city.${cityId}`;
}

export function cityChannelUrl(config: RealtimeConfig): string {
  const protocol = config.scheme === 'https' ? 'wss' : 'ws';
  return `${protocol}://${config.host}:${config.port}/app/${config.key}?protocol=7&client=castleroyale-mobile&version=1.0`;
}

type PusherFrame = {
  event: string;
  channel?: string;
  data?: string;
};

function isPusherProtocolEvent(event: string): boolean {
  return event.startsWith('pusher:') || event.startsWith('pusher_internal:');
}

/**
 * Subscribe to one city's private channel over the plain Pusher-protocol
 * handshake Reverb speaks. Returns an unsubscribe function.
 *
 * Reconnection, backoff and sequence-gap resync are Phase 40's and are
 * deliberately absent — this ships subscribe -> receive -> invalidate only.
 */
export function subscribeToCityChannel(options: CityChannelOptions): () => void {
  const { config, cityId, onEvent, getAccessToken } = options;
  const fetchImpl = options.fetchImpl ?? fetch;
  const socketFactory: SocketFactory =
    options.socketFactory ?? ((url) => new WebSocket(url) as unknown as SocketLike);

  const channelName = cityChannelName(cityId);
  const socket = socketFactory(cityChannelUrl(config));

  async function subscribe(socketId: string): Promise<void> {
    try {
      const accessToken = await getAccessToken();
      const response = await fetchImpl(config.auth_endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${accessToken ?? ''}`,
        },
        body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
      });

      if (!response.ok) {
        socket.close();
        return;
      }

      const body = (await response.json()) as { auth?: string };
      if (!body.auth) {
        socket.close();
        return;
      }

      socket.send(
        JSON.stringify({
          event: 'pusher:subscribe',
          data: { auth: body.auth, channel: channelName },
        }),
      );
    } catch {
      // A missed event costs a refresh; a wrong write costs correctness.
      // Swallow nothing silently on the console, but never touch the query
      // cache from here — just stop trying to use this connection.
      socket.close();
    }
  }

  socket.onmessage = (event) => {
    let frame: PusherFrame;
    try {
      frame = JSON.parse(event.data) as PusherFrame;
    } catch {
      return;
    }

    if (frame.event === 'pusher:connection_established') {
      let socketId: string | undefined;
      try {
        socketId = (JSON.parse(frame.data ?? '{}') as { socket_id?: string }).socket_id;
      } catch {
        socketId = undefined;
      }

      if (socketId) void subscribe(socketId);
      return;
    }

    if (frame.channel !== channelName) return;
    if (isPusherProtocolEvent(frame.event)) return;

    onEvent(frame.event);
  };

  socket.onerror = () => {
    socket.close();
  };

  return () => {
    socket.onopen = null;
    socket.onmessage = null;
    socket.onerror = null;
    socket.onclose = null;
    socket.close();
  };
}
