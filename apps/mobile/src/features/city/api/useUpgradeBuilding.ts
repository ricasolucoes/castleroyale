import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { Construction } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';

/**
 * Start a building upgrade.
 *
 * `onSettled` fires on both outcomes, not just a happy path: a refusal means
 * the server's state moved without us (another device spent the resources, an
 * order freed a slot), so the honest response to both outcomes is to refetch
 * truth and let the CTA re-derive itself — never to patch the cache
 * optimistically (docs/mobile/architecture.md).
 * `apiRequest` attaches a fresh Idempotency-Key per call, which is correct: a
 * retry after a refusal is a new logical operation, not a replay of the old one.
 */
export function useUpgradeBuilding() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (buildingCode: string) =>
      apiRequest<{ construction: Construction }>(
        `/game/city/buildings/${encodeURIComponent(buildingCode)}/upgrade`,
        { method: 'POST' },
        { authenticated: true },
      ),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['game', 'city'] }),
  });
}
