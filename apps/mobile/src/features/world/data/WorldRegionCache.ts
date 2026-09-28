import { createMMKV } from 'react-native-mmkv';
import type { WorldViewport } from '@castleroyale/contracts';

type WorldRegionStorage = {
  getString(key: string): string | undefined;
  set(key: string, value: string): void;
  remove(key: string): void;
};

const memoryStorage = new Map<string, string>();
const fallbackStorage: WorldRegionStorage = {
  getString: (key) => memoryStorage.get(key),
  set: (key, value) => memoryStorage.set(key, value),
  remove: (key) => {
    memoryStorage.delete(key);
  },
};

let storage: WorldRegionStorage | undefined;

function getStorage(): WorldRegionStorage {
  if (storage) return storage;

  try {
    storage = createMMKV({ id: 'world-regions' });
  } catch {
    // Expo Go does not ship Nitro Modules. Native builds still use persistent MMKV.
    storage = fallbackStorage;
  }

  return storage;
}

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
  const raw = getStorage().getString(cacheKey(worldId, minX, minY));
  if (!raw) return null;

  try {
    const value: unknown = JSON.parse(raw);
    return isViewport(value) ? value : null;
  } catch {
    getStorage().remove(cacheKey(worldId, minX, minY));
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
    getStorage().set(cacheKey(worldId, minX, minY), JSON.stringify(viewport));
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

  getStorage().set(cacheKey(worldId, minX, minY), JSON.stringify(mergedViewport));
}

export function worldRegionCacheKey(worldId: string, minX: number, minY: number): string {
  return cacheKey(worldId, minX, minY);
}
