import { useQuery } from '@tanstack/react-query';
import type { WorldViewport } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';
import { readWorldRegion, writeWorldRegion } from '@/features/world/data/WorldRegionCache';

export type ViewportBounds = { minX: number; maxX: number; minY: number; maxY: number };
export type ViewportData = { viewport: WorldViewport; cacheStatus: 'fresh' | 'stale' };

export function viewportQueryKey(bounds: ViewportBounds, worldId?: string) {
  return ['game', 'world', worldId, 'viewport', bounds.minX, bounds.maxX, bounds.minY, bounds.maxY] as const;
}

export async function fetchWorldViewport(bounds: ViewportBounds, worldId?: string): Promise<ViewportData> {
  const query = new URLSearchParams({
    min_x: String(bounds.minX),
    max_x: String(bounds.maxX),
    min_y: String(bounds.minY),
    max_y: String(bounds.maxY),
  });
  const cachedWorld = worldId ? readWorldRegion(worldId, bounds.minX, bounds.minY) : null;

  try {
    const viewport = await apiRequest<WorldViewport>(
      `/game/world/viewport?${query.toString()}`,
      {},
      { authenticated: true },
    );
    writeWorldRegion(viewport.world.id, bounds.minX, bounds.minY, viewport);

    return { viewport, cacheStatus: 'fresh' };
  } catch (error) {
    if (cachedWorld) return { viewport: cachedWorld, cacheStatus: 'stale' };
    throw error;
  }
}

function normalizeBounds(bounds: ViewportBounds): ViewportBounds {
  const width = Math.max(0, bounds.maxX - bounds.minX);
  const height = Math.max(0, bounds.maxY - bounds.minY);
  const margin = Math.max(width, height);
  
  return {
    minX: Math.floor(bounds.minX - margin),
    maxX: Math.ceil(bounds.maxX + margin),
    minY: Math.floor(bounds.minY - margin),
    maxY: Math.ceil(bounds.maxY + margin),
  };
}

export function useWorldViewport(bounds: ViewportBounds, worldId?: string) {
  const normalizedBounds = normalizeBounds(bounds);

  return useQuery({
    queryKey: viewportQueryKey(normalizedBounds, worldId),
    queryFn: () => fetchWorldViewport(normalizedBounds, worldId),
    enabled: Boolean(worldId),
  });
}
