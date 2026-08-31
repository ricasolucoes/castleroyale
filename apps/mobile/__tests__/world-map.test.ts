import type { WorldTile } from '@dominion/contracts';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const mockStorage = new Map<string, string>();

jest.mock('react-native-mmkv', () => ({
  createMMKV: () => ({
    getString: (key: string) => mockStorage.get(key),
    set: (key: string, value: string) => mockStorage.set(key, value),
    remove: (key: string) => mockStorage.delete(key),
  }),
}));

import { cullTiles, expandBounds } from '../src/features/world/rendering/cull';
import { groupTileBatches } from '../src/features/world/rendering/batches';
import { lodForZoom } from '../src/features/world/rendering/lod';
import { applyTileDelta } from '../src/features/world/rendering/delta';
import {
  readWorldRegion,
  worldRegionCacheKey,
  writeWorldRegion,
} from '../src/features/world/data/WorldRegionCache';
import { viewportQueryKey } from '../src/features/world/data/useWorldViewport';
import type { WorldViewport } from '@dominion/contracts';
import {
  clampZoom,
  MAX_MAP_ZOOM,
  MIN_MAP_ZOOM,
  useCameraStore,
} from '../src/features/world/state/cameraStore';

const tiles: WorldTile[] = [
  { id: '01H00000000000000000000000', region_id: '01H00000000000000000000001', x: 0, y: 0, terrain: 'plains' },
  { id: '01H00000000000000000000002', region_id: '01H00000000000000000000001', x: 1, y: 0, terrain: 'forest' },
  { id: '01H00000000000000000000003', region_id: '01H00000000000000000000001', x: 2, y: 0, terrain: 'hills' },
];

describe('world map camera and culling', () => {
  it('keeps map entities inside one batched Skia canvas', () => {
    const source = readFileSync(
      join(__dirname, '../src/features/world/components/MapCanvas.tsx'),
      'utf8',
    );

    expect(source.match(/<Canvas/g)).toHaveLength(1);
    expect(source).not.toContain('<Rect');
    expect(source).not.toMatch(/<\/?(Tile|Marker|Terrain)/);
    expect(source).not.toContain('terrainTiles.map');
  });

  it('expands bounds by a whole-screen margin and culls outside tiles', () => {
    const bounds = expandBounds({ minX: 0, maxX: 1, minY: 0, maxY: 0 }, 1);

    expect(bounds).toEqual({ minX: -1, maxX: 2, minY: -1, maxY: 1 });
    expect(cullTiles(tiles, bounds).map((tile) => tile.x)).toEqual([0, 1, 2]);
  });

  it('clamps zoom to the camera limits', () => {
    expect(clampZoom(0)).toBe(MIN_MAP_ZOOM);
    expect(clampZoom(99)).toBe(MAX_MAP_ZOOM);
  });

  it('selects the documented LOD tiers and stable terrain batches', () => {
    expect(lodForZoom(1)).toBe('far');
    expect(lodForZoom(2)).toBe('mid');
    expect(lodForZoom(3)).toBe('near');
    expect(groupTileBatches([tiles[1]!, tiles[0]!, tiles[2]!]).map((batch) => batch.terrain)).toEqual([
      'forest',
      'hills',
      'plains',
    ]);
  });

  it('resets camera to the player coordinate and clears stale selection', () => {
    useCameraStore.getState().selectCoordinate(4, 5);
    useCameraStore.getState().setCamera(8, 9, MAX_MAP_ZOOM);
    useCameraStore.getState().resetTo(2, 3);

    expect(useCameraStore.getState()).toMatchObject({
      centerX: 2,
      centerY: 3,
      zoom: 1,
      selectedX: null,
      selectedY: null,
    });
  });

  it('namespaces cached regions by world and applies coordinate deltas', () => {
    const viewport = {
      world: { id: 'world-1' },
      tiles,
    } as unknown as WorldViewport;

    writeWorldRegion('world-1', 0, 0, viewport);
    expect(readWorldRegion('world-1', 0, 0)).toEqual(viewport);
    expect(readWorldRegion('world-2', 0, 0)).toBeNull();
    expect(worldRegionCacheKey('world-1', 0, 0)).toContain('world:world-1:region:0:0');
    expect(viewportQueryKey({ minX: 0, maxX: 1, minY: 0, maxY: 1 }, 'world-1')).toEqual([
      'game',
      'world',
      'world-1',
      'viewport',
      0,
      1,
      0,
      1,
    ]);
    const replacement: WorldTile = {
      id: '01H00000000000000000000000',
      region_id: '01H00000000000000000000001',
      x: 0,
      y: 0,
      terrain: 'forest',
    };
    expect(applyTileDelta(tiles, [replacement])[0]?.terrain).toBe('forest');
  });

  it('processes a 4,096-tile fixture efficiently and bounds commands by visible batches', () => {
    const fixtureTiles: WorldTile[] = [];
    const terrains: WorldTile['terrain'][] = ['plains', 'forest', 'hills', 'mountains', 'river', 'road'];
    for (let y = 0; y < 64; y++) {
      for (let x = 0; x < 64; x++) {
        fixtureTiles.push({
          id: `tile-${x}-${y}`,
          region_id: 'region-1',
          x,
          y,
          terrain: terrains[(x + y) % terrains.length]!,
        });
      }
    }

    const start = performance.now();
    const bounds = { minX: 10, maxX: 20, minY: 10, maxY: 20 };
    const visible = cullTiles(fixtureTiles, bounds);
    const batches = groupTileBatches(visible);
    const duration = performance.now() - start;

    expect(duration).toBeLessThan(50);
    expect(visible.length).toBe(121);
    expect(batches.length).toBeLessThanOrEqual(terrains.length);
  });
});
