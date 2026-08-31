import { create } from 'zustand';

export const MIN_MAP_ZOOM = 0.75;
export const MAX_MAP_ZOOM = 4;

export type CameraState = {
  centerX: number;
  centerY: number;
  zoom: number;
  selectedX: number | null;
  selectedY: number | null;
  setCamera: (centerX: number, centerY: number, zoom: number) => void;
  selectCoordinate: (x: number, y: number) => void;
  clearSelection: () => void;
  resetTo: (x: number, y: number) => void;
};

export function clampZoom(zoom: number): number {
  return Math.min(MAX_MAP_ZOOM, Math.max(MIN_MAP_ZOOM, zoom));
}

export const useCameraStore = create<CameraState>((set) => ({
  centerX: 0,
  centerY: 0,
  zoom: 1,
  selectedX: null,
  selectedY: null,
  setCamera: (centerX, centerY, zoom) => set({ centerX, centerY, zoom: clampZoom(zoom) }),
  selectCoordinate: (selectedX, selectedY) => set({ selectedX, selectedY }),
  clearSelection: () => set({ selectedX: null, selectedY: null }),
  resetTo: (centerX, centerY) => set({ centerX, centerY, zoom: 1, selectedX: null, selectedY: null }),
}));
