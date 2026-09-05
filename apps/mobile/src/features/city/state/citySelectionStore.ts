import { create } from 'zustand';

export type CitySelectionState = {
  selectedSlot: string | null;
  selectSlot: (slot: string) => void;
  clearSelection: () => void;
};

export const useCitySelectionStore = create<CitySelectionState>((set) => ({
  selectedSlot: null,
  selectSlot: (selectedSlot) => set({ selectedSlot }),
  clearSelection: () => set({ selectedSlot: null }),
}));
