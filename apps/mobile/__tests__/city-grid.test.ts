import { computeSlotLayout } from '../src/features/city/rendering/grid';
import { useCitySelectionStore } from '../src/features/city/state/citySelectionStore';

describe('computeSlotLayout', () => {
  it('fills a 390pt frame edge-to-edge at the base tile unit', () => {
    expect(computeSlotLayout(390, 18, 48, 44)).toEqual({ columns: 8, tileSize: 48.75, rows: 3 });
  });

  it('decrements columns to keep the touch area at or above the 44pt floor', () => {
    expect(computeSlotLayout(100, 18, 32, 44)).toEqual({ columns: 2, tileSize: 50, rows: 9 });
  });

  it('never drops below one column even when the floor cannot be met', () => {
    expect(computeSlotLayout(40, 5, 48, 44)).toEqual({ columns: 1, tileSize: 40, rows: 5 });
  });

  it('does not crash or divide by zero on the pre-measurement frame', () => {
    expect(computeSlotLayout(0, 18, 48, 44)).toEqual({ columns: 1, tileSize: 0, rows: 18 });
  });

  it('returns zero rows for an empty roster', () => {
    expect(computeSlotLayout(390, 0, 48, 44)).toMatchObject({ rows: 0 });
  });

  it('is count-agnostic: no slot total is hardcoded', () => {
    expect(computeSlotLayout(390, 40, 48, 44)).toMatchObject({ rows: 5 });
  });
});

describe('useCitySelectionStore', () => {
  beforeEach(() => {
    useCitySelectionStore.setState({ selectedSlot: null });
  });

  it('starts with no selection', () => {
    expect(useCitySelectionStore.getState().selectedSlot).toBeNull();
  });

  it('selects a slot by id', () => {
    useCitySelectionStore.getState().selectSlot('plot_07');
    expect(useCitySelectionStore.getState().selectedSlot).toBe('plot_07');
  });

  it('clears the selection back to null', () => {
    useCitySelectionStore.getState().selectSlot('plot_07');
    useCitySelectionStore.getState().clearSelection();
    expect(useCitySelectionStore.getState().selectedSlot).toBeNull();
  });
});
