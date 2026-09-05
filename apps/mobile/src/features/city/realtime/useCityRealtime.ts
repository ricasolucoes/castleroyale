import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { RealtimeConfig } from '@castleroyale/contracts';

import { getTokens } from '@/features/auth/SecureStorage';
import { subscribeToCityChannel, type SocketFactory } from './cityChannel';

/**
 * Subscribes the current screen to its own private city channel and
 * invalidates the `['game', 'city']` query cache on any event, refetching
 * over HTTP rather than trusting a pushed payload.
 *
 * Never writes slot or building data into a store — the query cache is the
 * only client-side copy of server state (docs/mobile/architecture.md §
 * The state rule).
 */
export function useCityRealtime(
  cityId: string | null,
  config: RealtimeConfig | null,
  socketFactory?: SocketFactory,
): void {
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!cityId || !config || config.key === '') return;

    const unsubscribe = subscribeToCityChannel({
      config,
      cityId,
      getAccessToken: async () => (await getTokens()).access ?? undefined,
      onEvent: () => {
        void queryClient.invalidateQueries({ queryKey: ['game', 'city'] });
      },
      socketFactory,
    });

    return unsubscribe;
    // `socketFactory` is intentionally excluded: production callers never
    // pass one (it defaults to a real `WebSocket`), and re-subscribing on a
    // test-only factory identity change is not a real-world case worth
    // reconnecting for.
  }, [cityId, config?.key, config?.host, config?.port, config?.scheme, config?.auth_endpoint, queryClient]);
}
