import { useQuery } from '@tanstack/react-query';
import type { TechnologyTreeData } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';

/**
 * The one `['game', 'technology']` query, mirroring `useCityQuery.ts`. Both the
 * tree screen and its detail sheet read this cache; TanStack Query dedupes the
 * identical key.
 *
 * `refetchInterval` reuses the app's global `staleTime` (30s) the same way
 * `useCityQuery` does — a research completing in the background (no realtime
 * event exists for this yet, per 10-UI-SPEC.md §F) should still surface within
 * one polling window, not only on the next mutation.
 */
export function useTechnologyQuery() {
  return useQuery({
    queryKey: ['game', 'technology'],
    queryFn: () =>
      apiRequest<TechnologyTreeData>('/game/technologies', {}, { authenticated: true }),
    refetchInterval: 30_000,
  });
}
