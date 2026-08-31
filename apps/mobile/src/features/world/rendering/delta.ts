import type { WorldTile } from '@dominion/contracts';

export function applyTileDelta(current: readonly WorldTile[], delta: readonly WorldTile[]): WorldTile[] {
  const byCoordinate = new Map(current.map((tile) => [`${tile.x}:${tile.y}`, tile]));
  for (const tile of delta) byCoordinate.set(`${tile.x}:${tile.y}`, tile);

  return [...byCoordinate.values()].sort((first, second) => first.y - second.y || first.x - second.x);
}
