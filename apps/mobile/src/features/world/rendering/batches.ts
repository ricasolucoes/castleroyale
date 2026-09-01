import type { WorldTile } from '@castleroyale/contracts';

export type TileBatch = { terrain: WorldTile['terrain']; tiles: WorldTile[] };

export function groupTileBatches(tiles: readonly WorldTile[]): TileBatch[] {
  const groups = new Map<WorldTile['terrain'], WorldTile[]>();
  for (const tile of tiles) {
    const group = groups.get(tile.terrain) ?? [];
    group.push(tile);
    groups.set(tile.terrain, group);
  }

  return [...groups.entries()]
    .sort(([first], [second]) => first.localeCompare(second))
    .map(([terrain, groupedTiles]) => ({ terrain, tiles: groupedTiles }));
}
