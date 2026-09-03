import type { WorldTile } from '@castleroyale/contracts';
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
  buildMapDrawCommands,
  type MapDrawCommand,
  type MapMarker,
} from '../src/features/world/rendering/commands';
import {
  readWorldRegion,
  worldRegionCacheKey,
  writeWorldRegion,
} from '../src/features/world/data/WorldRegionCache';
import { viewportQueryKey } from '../src/features/world/data/useWorldViewport';
import type { WorldViewport } from '@castleroyale/contracts';
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

function isMarkerCommand(
  command: MapDrawCommand,
): command is Extract<MapDrawCommand, { kind: 'marker' }> {
  return command.kind === 'marker';
}

function isTerrainCommand(
  command: MapDrawCommand,
): command is Extract<MapDrawCommand, { kind: 'terrain' }> {
  return command.kind === 'terrain';
}

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
    expect(source).not.toContain('cities.map');
    expect(source).not.toContain('<Circle');
    expect(source).not.toContain('<Path');
    expect(source).not.toMatch(/\.map\([^)]*\)\s*=>\s*\(?\s*</);
    expect(source.match(/<Picture/g)).toHaveLength(1);
    expect(source).toContain('lodForZoom');
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

describe('map draw commands', () => {
  const markers: MapMarker[] = [
    { x: 0, y: 0, is_player_city: true },
    { x: 1, y: 0, is_player_city: false },
    { x: 2, y: 0, is_player_city: false },
    { x: 50, y: 50, is_player_city: false },
  ];
  const bounds = { minX: 0, maxX: 2, minY: 0, maxY: 0 };
  const tileSize = 24;

  it('draws only the player city dot at the far LOD and never the out-of-bounds marker', () => {
    for (const lod of ['far', 'mid', 'near'] as const) {
      const points = buildMapDrawCommands({ tiles, markers, bounds, tileSize, lod })
        .filter(isMarkerCommand)
        .flatMap((command) => command.points);
      expect(points.some((point) => point.cx === 1212 && point.cy === 1212)).toBe(false);
    }

    const farCommands = buildMapDrawCommands({ tiles, markers, bounds, tileSize, lod: 'far' }).filter(
      isMarkerCommand,
    );
    const cityCommands = farCommands.filter((command) => command.marker === 'city');
    const playerCommands = farCommands.filter((command) => command.marker === 'player');

    expect(cityCommands).toHaveLength(0);
    expect(playerCommands).toHaveLength(1);
    expect(playerCommands[0]).toMatchObject({ shape: 'dot', points: [{ cx: 12, cy: 12, r: 6 }] });
  });

  it('batches every other city into one dot command and the player into one disc at the mid LOD', () => {
    const midCommands = buildMapDrawCommands({ tiles, markers, bounds, tileSize, lod: 'mid' }).filter(
      isMarkerCommand,
    );
    const cityCommands = midCommands.filter((command) => command.marker === 'city');
    const playerCommands = midCommands.filter((command) => command.marker === 'player');

    expect(cityCommands).toHaveLength(1);
    expect(cityCommands[0]).toMatchObject({ shape: 'dot' });
    expect(cityCommands[0]?.points).toHaveLength(2);
    expect(playerCommands).toHaveLength(1);
    expect(playerCommands[0]).toMatchObject({ shape: 'disc' });
    expect(playerCommands[0]?.points).toHaveLength(1);
  });

  it('adds ring commands after every non-ring marker command at the near LOD', () => {
    const nearCommands = buildMapDrawCommands({ tiles, markers, bounds, tileSize, lod: 'near' }).filter(
      isMarkerCommand,
    );
    const ringIndexes = nearCommands
      .map((command, index) => (command.shape === 'ring' ? index : -1))
      .filter((index) => index >= 0);
    const nonRingIndexes = nearCommands
      .map((command, index) => (command.shape === 'ring' ? -1 : index))
      .filter((index) => index >= 0);

    expect(ringIndexes.length).toBeGreaterThan(0);
    expect(Math.min(...ringIndexes)).toBeGreaterThan(Math.max(...nonRingIndexes));

    const cityDot = nearCommands.find((command) => command.marker === 'city' && command.shape === 'dot');
    const playerDisc = nearCommands.find((command) => command.marker === 'player' && command.shape === 'disc');
    expect(cityDot?.points).toHaveLength(2);
    expect(playerDisc?.points).toHaveLength(1);
  });

  it('is deterministic and keeps terrain batches in sorted terrain order', () => {
    const first = buildMapDrawCommands({ tiles, markers, bounds, tileSize, lod: 'near' });
    const second = buildMapDrawCommands({ tiles, markers, bounds, tileSize, lod: 'near' });

    expect(JSON.stringify(first)).toEqual(JSON.stringify(second));

    const terrainOrder = first.filter(isTerrainCommand).map((command) => command.terrain);
    expect(terrainOrder).toEqual(['forest', 'hills', 'plains']);
  });
});
