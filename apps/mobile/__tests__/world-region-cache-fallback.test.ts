jest.mock('react-native-mmkv', () => ({
  createMMKV: () => {
    throw new Error('Nitro Modules unavailable');
  },
}));

import { readWorldRegion, writeWorldRegion } from '../src/features/world/data/WorldRegionCache';
import type { WorldViewport } from '@castleroyale/contracts';

describe('WorldRegionCache runtime fallback', () => {
  it('keeps the world route usable when MMKV is unavailable', () => {
    const viewport = {
      world: { id: 'world-1' },
      tiles: [],
    } as unknown as WorldViewport;

    expect(() => writeWorldRegion('world-1', 0, 0, viewport)).not.toThrow();
    expect(readWorldRegion('world-1', 0, 0)).toEqual(viewport);
  });
});
