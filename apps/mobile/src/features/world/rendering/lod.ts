export type MapLod = 'far' | 'mid' | 'near';

export const MID_LOD_ZOOM = 1.5;
export const NEAR_LOD_ZOOM = 3;

export function lodForZoom(zoom: number): MapLod {
  if (zoom >= NEAR_LOD_ZOOM) return 'near';
  if (zoom >= MID_LOD_ZOOM) return 'mid';

  return 'far';
}
