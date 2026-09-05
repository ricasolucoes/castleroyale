import type { ResourceBundle, ResourceRate } from '@castleroyale/contracts';
import { RESOURCE_KEYS } from '@/shared/components/resourceIcons';

/**
 * Pure, unit-testable, no RN/React import — mirrors the convention already
 * established by `features/city/rendering/grid.ts`.
 */
export type ResourceSnapshot = {
  current: ResourceBundle;
  capacity: ResourceBundle;
  rate: ResourceRate; // signed, integer per hour
  capturedAt: number; // cityQuery.dataUpdatedAt
};

/**
 * Floating-point maths here is not an ADR-010 violation: ADR-010 governs
 * stored, authoritative economy state on the server. This function is purely
 * a client display projection that is never fed into an affordability check
 * (08-CONTEXT.md, Client decision) and always `Math.floor`s to an integer
 * before render.
 */
export function interpolateResources(snapshot: ResourceSnapshot, now: number): ResourceBundle {
  const elapsedMs = Math.max(0, now - snapshot.capturedAt); // never extrapolate backward
  const result = {} as ResourceBundle;

  for (const key of RESOURCE_KEYS) {
    const base = snapshot.current[key] ?? 0;
    const cap = snapshot.capacity[key] ?? 0;
    const ratePerMs = (snapshot.rate[key] ?? 0) / 3_600_000;
    const projected = base + ratePerMs * elapsedMs;
    result[key] = Math.floor(cap > 0 ? Math.max(0, Math.min(cap, projected)) : Math.max(0, projected));
  }

  return result;
}
