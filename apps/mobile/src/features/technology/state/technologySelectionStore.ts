import { create } from 'zustand';

export type TechnologySelectionState = {
  selectedTechnology: string | null;
  selectTechnology: (code: string) => void;
  clearSelection: () => void;
};

/**
 * Client state only — mirrors `citySelectionStore.ts` line for line. The
 * technology itself is always derived from the `['game', 'technology']` query
 * cache, never copied in here (docs/mobile/architecture.md's state rule).
 */
export const useTechnologySelectionStore = create<TechnologySelectionState>((set) => ({
  selectedTechnology: null,
  selectTechnology: (selectedTechnology) => set({ selectedTechnology }),
  clearSelection: () => set({ selectedTechnology: null }),
}));
