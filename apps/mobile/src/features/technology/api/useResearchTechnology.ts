import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { ResearchOrder } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';

/**
 * Start researching a technology, mirroring `useUpgradeBuilding.ts` exactly.
 *
 * Both invalidations are required: a completed research changes
 * `resources.rate` (production/build/march effects), so the city snapshot is
 * stale too, not just the tree. `onSettled` fires on both outcomes, not just a
 * happy path — a refusal means the server's state moved without us (another
 * device started a research first, an order just completed and freed the
 * slot), so the honest response to both outcomes is to refetch truth and let
 * the CTA re-derive itself, never to patch the cache optimistically
 * (docs/mobile/architecture.md).
 */
export function useResearchTechnology() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (technologyCode: string) =>
      apiRequest<{ research: ResearchOrder }>(
        `/game/technologies/${encodeURIComponent(technologyCode)}/research`,
        { method: 'POST' },
        { authenticated: true },
      ),
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['game', 'technology'] });
      queryClient.invalidateQueries({ queryKey: ['game', 'city'] });
    },
  });
}
