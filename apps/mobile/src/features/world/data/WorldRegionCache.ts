import { createMMKV } from 'react-native-mmkv';
import type { WorldViewport } from '@castleroyale/contracts';

const storage = createMMKV({ id: 'world-regions' });

function cacheKey(worldId: string, minX: number, minY: number): string {
  const regionX = Math.floor(minX / 16);
  const regionY = Math.floor(minY / 16);

  return `world:${worldId}:region:${regionX}:${regionY}`;
}

function isViewport(value: unknown): value is WorldViewport {
  if (typeof value !== 'object' || value === null || !('world' in value) || !('tiles' in value)) {
    return false;
  }

  return Array.isArray(value.tiles) && typeof value.world === 'object' && value.world !== null;
}

export function readWorldRegion(
  worldId: string,
  minX: number,
  minY: number,
): WorldViewport | null {
  const raw = storage.getString(cacheKey(worldId, minX, minY));
  if (!raw) return null;

  try {
    const value: unknown = JSON.parse(raw);
    return isViewport(value) ? value : null;
  } catch {
    storage.remove(cacheKey(worldId, minX, minY));
    return null;
  }
}

export function writeWorldRegion(
  worldId: string,
  minX: number,
  minY: number,
  viewport: WorldViewport,
): void {
  const existing = readWorldRegion(worldId, minX, minY);
  if (!existing) {
    storage.set(cacheKey(worldId, minX, minY), JSON.stringify(viewport));
    return;
  }

  const mergedTiles = [...existing.tiles];
  for (const newTile of viewport.tiles) {
    const idx = mergedTiles.findIndex(t => t.x === newTile.x && t.y === newTile.y);
    if (idx >= 0) {
      mergedTiles[idx] = newTile;
    } else {
      mergedTiles.push(newTile);
    }
  }

  const mergedViewport: WorldViewport = {
    ...viewport,
    tiles: mergedTiles,
  };

  storage.set(cacheKey(worldId, minX, minY), JSON.stringify(mergedViewport));
}

export function worldRegionCacheKey(worldId: string, minX: number, minY: number): string {
  return cacheKey(worldId, minX, minY);
}
