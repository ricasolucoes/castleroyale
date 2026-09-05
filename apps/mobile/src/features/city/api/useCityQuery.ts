import { useQuery } from '@tanstack/react-query';
import type { CityData } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';

/**
 * The one `['game', 'city']` query. `ResourceBar` and the city screen both call
 * this; TanStack Query dedupes the identical key, so it is one request and one
 * cache entry, not two.
 *
 * `refetchInterval` reuses the app's global `staleTime` (30s, set in
 * `app/_layout.tsx`) rather than introducing a second cadence number.
 */
export function useCityQuery() {
  return useQuery({
    queryKey: ['game', 'city'],
    queryFn: () => apiRequest<CityData>('/game/city', {}, { authenticated: true }),
    refetchInterval: 30_000,
  });
}
