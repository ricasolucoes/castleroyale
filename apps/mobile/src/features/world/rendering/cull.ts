import type { WorldTile } from '@dominion/contracts';

export type TileBounds = { minX: number; maxX: number; minY: number; maxY: number };

export function cameraToBounds(
  centerX: number,
  centerY: number,
  zoom: number,
  screenWidth: number,
  screenHeight: number,
  tileSize: number,
): TileBounds {
  const halfWidth = screenWidth / 2 / zoom / tileSize;
  const halfHeight = screenHeight / 2 / zoom / tileSize;

  return {
    minX: Math.floor(centerX - halfWidth),
    maxX: Math.ceil(centerX + halfWidth),
    minY: Math.floor(centerY - halfHeight),
    maxY: Math.ceil(centerY + halfHeight),
  };
}

export function expandBounds(bounds: TileBounds, margin: number): TileBounds {
  const safeMargin = Math.max(0, Math.ceil(margin));

  return {
    minX: bounds.minX - safeMargin,
    maxX: bounds.maxX + safeMargin,
    minY: bounds.minY - safeMargin,
    maxY: bounds.maxY + safeMargin,
  };
}

export function cullTiles(tiles: readonly WorldTile[], bounds: TileBounds): WorldTile[] {
  return tiles.filter(
    (tile) =>
      tile.x >= bounds.minX &&
      tile.x <= bounds.maxX &&
      tile.y >= bounds.minY &&
      tile.y <= bounds.maxY,
  );
}
