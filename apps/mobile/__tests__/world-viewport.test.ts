import { fetchWorldViewport, viewportQueryKey } from '../src/features/world/data/useWorldViewport';
import { readWorldRegion, writeWorldRegion, worldRegionCacheKey } from '../src/features/world/data/WorldRegionCache';
import { apiRequest } from '../src/api/client';
import type { WorldViewport, WorldTile } from '@dominion/contracts';

jest.mock('../src/api/client', () => ({
  apiRequest: jest.fn(),
}));

const mockStorage = new Map<string, string>();
jest.mock('react-native-mmkv', () => ({
  createMMKV: () => ({
    getString: (key: string) => mockStorage.get(key) || undefined,
    set: (key: string, value: string) => mockStorage.set(key, value),
    remove: (key: string) => mockStorage.delete(key),
  }),
}));

describe('World Viewport Data', () => {
  beforeEach(() => {
    mockStorage.clear();
    jest.clearAllMocks();
  });

  const mockViewport: WorldViewport = {
    world: { id: 'world-1', name: 'Test World' },
    player: { id: 'player-1', name: 'Test Player' },
    bounds: { min_x: 0, max_x: 10, min_y: 0, max_y: 10 },
    tiles: [
      { id: 't1', region_id: 'r1', x: 0, y: 0, terrain: 'plains' },
    ],
  } as unknown as WorldViewport;

  it('constructs exact viewport URL and query key', () => {
    const bounds = { minX: -10, maxX: 10, minY: -5, maxY: 15 };
    const key = viewportQueryKey(bounds, 'world-1');
    expect(key).toEqual(['game', 'world', 'world-1', 'viewport', -10, 10, -5, 15]);
  });

  it('cache round-trip and invalid cache rejection', () => {
    writeWorldRegion('world-1', 0, 0, mockViewport);
    const cached = readWorldRegion('world-1', 0, 0);
    expect(cached).toEqual(mockViewport);
    
    // Invalid cache rejection
    mockStorage.set(worldRegionCacheKey('world-1', 0, 0), 'invalid json {');
    expect(readWorldRegion('world-1', 0, 0)).toBeNull();

    mockStorage.set(worldRegionCacheKey('world-1', 0, 0), JSON.stringify({ not: 'a viewport' }));
    expect(readWorldRegion('world-1', 0, 0)).toBeNull();
  });

  it('stale fallback after a network error', async () => {
    writeWorldRegion('world-1', 0, 0, mockViewport);
    (apiRequest as jest.Mock).mockRejectedValue(new Error('Network error'));
    
    const result = await fetchWorldViewport({ minX: 0, maxX: 10, minY: 0, maxY: 10 }, 'world-1');
    expect(result.cacheStatus).toBe('stale');
    expect(result.viewport).toEqual(mockViewport);
  });

  it('delta merge replaces the same (x,y) while unrelated cached tiles remain', async () => {
    // We want to test delta merge in writeWorldRegion
    const initialTiles = [
      { id: 't1', region_id: 'r1', x: 0, y: 0, terrain: 'plains' },
      { id: 't2', region_id: 'r1', x: 1, y: 0, terrain: 'forest' }
    ] as WorldTile[];
    
    writeWorldRegion('world-1', 0, 0, { ...mockViewport, tiles: initialTiles });
    
    const newTiles = [
      { id: 't3', region_id: 'r1', x: 0, y: 0, terrain: 'hills' }, // replaces (0,0)
      { id: 't4', region_id: 'r1', x: 2, y: 0, terrain: 'mountains' } // adds (2,0)
    ] as WorldTile[];
    
    writeWorldRegion('world-1', 0, 0, { ...mockViewport, tiles: newTiles });
    
    const merged = readWorldRegion('world-1', 0, 0);
    expect(merged?.tiles).toHaveLength(3);
    
    // t3 replaced t1 at (0,0)
    expect(merged?.tiles.find(t => t.x === 0 && t.y === 0)).toEqual(newTiles[0]);
    // t2 remains at (1,0)
    expect(merged?.tiles.find(t => t.x === 1 && t.y === 0)).toEqual(initialTiles[1]);
    // t4 added at (2,0)
    expect(merged?.tiles.find(t => t.x === 2 && t.y === 0)).toEqual(newTiles[1]);
  });
});
