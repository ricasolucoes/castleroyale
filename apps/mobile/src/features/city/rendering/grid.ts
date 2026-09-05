export type SlotLayout = {
  columns: number;
  tileSize: number;
  rows: number;
};

/**
 * Fit a server-driven number of plots into a runtime-measured frame.
 *
 * The count is never assumed: today a starter city has 18 plots and the
 * building catalogue grows, so the grid is derived, never authored.
 */
export function computeSlotLayout(
  frameWidth: number,
  slotCount: number,
  baseTileUnit: number,
  minTouchTarget: number,
): SlotLayout {
  const width = Math.max(0, frameWidth);
  let columns = Math.max(1, Math.floor(width / baseTileUnit));
  let tileSize = columns > 0 ? width / columns : 0;

  // Visual size may shrink in a dense grid; the touch area never does. Drop a
  // column rather than ship a plot the thumb cannot reliably hit.
  while (tileSize < minTouchTarget && columns > 1) {
    columns -= 1;
    tileSize = width / columns;
  }

  return { columns, tileSize, rows: Math.ceil(Math.max(0, slotCount) / columns) };
}
