/**
 * Pure Skia draw-command builder for the world map.
 *
 * No React and no Skia import here on purpose: this module only turns tiles,
 * markers, culling bounds and the current LOD tier into a deterministic list
 * of draw commands. `MapCanvas.tsx` is the only place that touches the Skia
 * recorder — this file is unit-testable without a canvas.
 */

import type { WorldTile } from '@castleroyale/contracts';

import { cullTiles, type TileBounds } from './cull';
import { groupTileBatches } from './batches';
import type { MapLod } from './lod';

export type MapMarker = { x: number; y: number; is_player_city: boolean };

export type MapDrawCommand =
  | {
      kind: 'terrain';
      terrain: WorldTile['terrain'];
      rects: { x: number; y: number; width: number; height: number }[];
    }
  | {
      kind: 'marker';
      marker: 'player' | 'city';
      shape: 'dot' | 'disc' | 'ring';
      points: { cx: number; cy: number; r: number }[];
    };

type BuildMapDrawCommandsInput = {
  tiles: readonly WorldTile[];
  markers: readonly MapMarker[];
  bounds: TileBounds;
  tileSize: number;
  lod: MapLod;
};

function isMarkerInBounds(marker: MapMarker, bounds: TileBounds): boolean {
  return (
    marker.x >= bounds.minX &&
    marker.x <= bounds.maxX &&
    marker.y >= bounds.minY &&
    marker.y <= bounds.maxY
  );
}

function toMarkerPoint(
  marker: MapMarker,
  bounds: TileBounds,
  tileSize: number,
  r: number,
): { cx: number; cy: number; r: number } {
  return {
    cx: (marker.x - bounds.minX + 0.5) * tileSize,
    cy: (marker.y - bounds.minY + 0.5) * tileSize,
    r,
  };
}

function pushMarkerCommand(
  commands: MapDrawCommand[],
  marker: 'player' | 'city',
  shape: 'dot' | 'disc' | 'ring',
  source: readonly MapMarker[],
  bounds: TileBounds,
  tileSize: number,
  r: number,
): void {
  if (source.length === 0) return;

  commands.push({
    kind: 'marker',
    marker,
    shape,
    points: source.map((entry) => toMarkerPoint(entry, bounds, tileSize, r)),
  });
}

/**
 * Deterministic and pure: identical input always produces a deep-equal
 * output. Order: terrain batches (already sorted by terrain), then city
 * commands, then player commands, then ring commands.
 */
export function buildMapDrawCommands(input: BuildMapDrawCommandsInput): MapDrawCommand[] {
  const { tiles, markers, bounds, tileSize, lod } = input;
  const commands: MapDrawCommand[] = [];

  for (const batch of groupTileBatches(cullTiles(tiles, bounds))) {
    commands.push({
      kind: 'terrain',
      terrain: batch.terrain,
      rects: batch.tiles.map((tile) => ({
        x: (tile.x - bounds.minX) * tileSize,
        y: (tile.y - bounds.minY) * tileSize,
        width: tileSize,
        height: tileSize,
      })),
    });
  }

  const visibleMarkers = markers.filter((marker) => isMarkerInBounds(marker, bounds));
  const playerMarkers = visibleMarkers.filter((marker) => marker.is_player_city);
  const cityMarkers = visibleMarkers.filter((marker) => !marker.is_player_city);

  if (lod === 'far') {
    pushMarkerCommand(commands, 'player', 'dot', playerMarkers, bounds, tileSize, tileSize / 4);
    return commands;
  }

  pushMarkerCommand(commands, 'city', 'dot', cityMarkers, bounds, tileSize, tileSize / 4);
  pushMarkerCommand(commands, 'player', 'disc', playerMarkers, bounds, tileSize, tileSize / 3);

  if (lod === 'near') {
    pushMarkerCommand(commands, 'city', 'ring', cityMarkers, bounds, tileSize, tileSize / 2);
    pushMarkerCommand(commands, 'player', 'ring', playerMarkers, bounds, tileSize, tileSize / 2);
  }

  return commands;
}
