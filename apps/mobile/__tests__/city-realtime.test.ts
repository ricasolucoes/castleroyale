import {
  cityChannelName,
  cityChannelUrl,
  subscribeToCityChannel,
  type SocketLike,
} from '../src/features/city/realtime/cityChannel';

const REALTIME_CONFIG = {
  key: 'k',
  host: 'rt.example',
  port: 443,
  scheme: 'https' as const,
  auth_endpoint: 'https://rt.example/broadcasting/auth',
};

class FakeSocket implements SocketLike {
  sent: string[] = [];
  closed = 0;
  onopen: (() => void) | null = null;
  onmessage: ((event: { data: string }) => void) | null = null;
  onerror: ((error: unknown) => void) | null = null;
  onclose: (() => void) | null = null;

  send(data: string): void {
    this.sent.push(data);
  }

  close(): void {
    this.closed += 1;
  }

  receive(payload: Record<string, unknown>): void {
    this.onmessage?.({ data: JSON.stringify(payload) });
  }
}

describe('cityChannelName', () => {
  it('namespaces the private channel with a city id', () => {
    expect(cityChannelName('01HZY0000000000000000000')).toBe(
      'private-city.01HZY0000000000000000000',
    );
  });
});

describe('cityChannelUrl', () => {
  it('builds a wss url for an https scheme', () => {
    expect(
      cityChannelUrl({ scheme: 'https', host: 'rt.example', port: 443, key: 'k', auth_endpoint: 'x' }),
    ).toBe('wss://rt.example:443/app/k?protocol=7&client=castleroyale-mobile&version=1.0');
  });

  it('builds a ws url for an http scheme', () => {
    expect(
      cityChannelUrl({ scheme: 'http', host: 'rt.example', port: 8081, key: 'k', auth_endpoint: 'x' }),
    ).toBe('ws://rt.example:8081/app/k?protocol=7&client=castleroyale-mobile&version=1.0');
  });
});

describe('subscribeToCityChannel', () => {
  function setUp(fetchImpl?: typeof fetch) {
    const socket = new FakeSocket();
    const onEvent = jest.fn();
    const unsubscribe = subscribeToCityChannel({
      config: REALTIME_CONFIG,
      cityId: 'city-x',
      onEvent,
      getAccessToken: async () => 'access-token-123',
      socketFactory: () => socket,
      fetchImpl,
    });

    return { socket, onEvent, unsubscribe };
  }

  it('authorises with the auth endpoint and subscribes once the socket connects', async () => {
    const fetchMock = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ auth: 'signed-auth-value' }),
    });

    const { socket } = setUp(fetchMock as unknown as typeof fetch);

    socket.receive({ event: 'pusher:connection_established', data: JSON.stringify({ socket_id: '1.2' }) });
    // Let the async subscribe() microtasks resolve.
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(REALTIME_CONFIG.auth_endpoint);
    const body = JSON.parse(init.body as string) as { socket_id: string; channel_name: string };
    expect(body.socket_id).toBe('1.2');
    expect(body.channel_name).toBe('private-city.city-x');
    const headers = init.headers as Record<string, string>;
    expect(headers['Authorization']).toBe('Bearer access-token-123');

    expect(socket.sent).toHaveLength(1);
    const subscribeFrame = JSON.parse(socket.sent[0]!) as {
      event: string;
      data: { auth: string; channel: string };
    };
    expect(subscribeFrame.event).toBe('pusher:subscribe');
    expect(subscribeFrame.data.auth).toBe('signed-auth-value');
    expect(subscribeFrame.data.channel).toBe('private-city.city-x');
  });

  it('calls onEvent exactly once for a state-changed event on this channel', () => {
    const { socket, onEvent } = setUp();

    socket.receive({ event: 'city.state_changed', channel: 'private-city.city-x', data: '{}' });

    expect(onEvent).toHaveBeenCalledTimes(1);
    expect(onEvent).toHaveBeenCalledWith('city.state_changed');
  });

  it('never calls onEvent for a pusher-internal frame on this channel', () => {
    const { socket, onEvent } = setUp();

    socket.receive({
      event: 'pusher_internal:subscription_succeeded',
      channel: 'private-city.city-x',
      data: '{}',
    });

    expect(onEvent).not.toHaveBeenCalled();
  });

  it('never calls onEvent for an event on a different channel', () => {
    const { socket, onEvent } = setUp();

    socket.receive({ event: 'city.state_changed', channel: 'private-city.some-other-city', data: '{}' });

    expect(onEvent).not.toHaveBeenCalled();
  });

  it('closes the socket and clears handlers on unsubscribe', () => {
    const { socket, unsubscribe } = setUp();

    unsubscribe();

    expect(socket.closed).toBe(1);
    expect(socket.onmessage).toBeNull();
    expect(socket.onerror).toBeNull();
    expect(socket.onclose).toBeNull();
  });

  it('closes the socket on a connection error', () => {
    const { socket } = setUp();

    socket.onerror?.(new Error('boom'));

    expect(socket.closed).toBe(1);
  });
});
