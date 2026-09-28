import React from 'react';
import { render } from '@testing-library/react-native';
import type { CitySlot as CitySlotData } from '@castleroyale/contracts';
import { terrainColors } from '@castleroyale/tooling/design-tokens';

import { CitySlot } from '../src/features/city/components/CitySlot';
import { getBuildingAsset } from '../src/features/city/buildingAssets';
import { WorldArtGallery } from '../src/features/world/components/WorldArtGallery';
import { HudPanel } from '../src/shared/components/HudPanel';

jest.mock('../src/i18n/useTranslation', () => ({
  useTranslation: () => ({
    t: (key: string, params?: Record<string, string | number>) =>
      params ? `${key} ${JSON.stringify(params)}` : key,
  }),
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }),
}));

function plot(building: CitySlotData['building']): CitySlotData {
  return { slot: 'plot_01', building } as CitySlotData;
}

describe('terrain is readable without colour', () => {
  it('gives every terrain its own base colour', () => {
    // Regression guard. `plains` and `forest` were both painted with the
    // theme's `success` green, which made two of the six terrains literally
    // the same pixel value on the map.
    const bases = Object.values(terrainColors).map((terrain) => terrain.base);
    expect(new Set(bases).size).toBe(bases.length);
  });

  it('separates each terrain from its own detail tone', () => {
    for (const [terrain, tones] of Object.entries(terrainColors)) {
      expect(tones.base).not.toBe(tones.detail);
      expect(terrain).toBeTruthy();
    }
  });

  it('covers every terrain the map can draw', () => {
    expect(Object.keys(terrainColors).sort()).toEqual([
      'forest',
      'hills',
      'mountains',
      'plains',
      'river',
      'road',
    ]);
  });
});

describe('HUD never blocks the scene', () => {
  it('is transparent to touch so the world underneath stays draggable', () => {
    const tree = render(
      <HudPanel corner="top-left">
        <></>
      </HudPanel>,
    ).toJSON();

    // The panel floats over a pannable map and a scrollable city. If it ever
    // stops being pointer-transparent, a thumb landing on it dies there
    // instead of dragging the world.
    expect(tree).not.toBeNull();
    expect(Array.isArray(tree)).toBe(false);
    expect((tree as { props: Record<string, unknown> }).props['pointerEvents']).toBe('none');
  });

  it('pins itself to the corner it was asked for', () => {
    const tree = render(
      <HudPanel corner="bottom-right">
        <></>
      </HudPanel>,
    ).toJSON() as unknown as { props: { style: Record<string, unknown>[] } };

    const style = Object.assign({}, ...[tree.props.style].flat(Infinity).filter(Boolean));
    expect(style['position']).toBe('absolute');
    expect(style['bottom']).toBeGreaterThan(0);
    expect(style['right']).toBeGreaterThan(0);
    expect(style['top']).toBeUndefined();
    expect(style['left']).toBeUndefined();
  });
});

describe('world art reference panel', () => {
  it('keeps all four generated map references discoverable in the client', () => {
    const { getByLabelText } = render(<WorldArtGallery />);

    expect(getByLabelText('world.art_world_overview')).toBeTruthy();
    expect(getByLabelText('world.art_region_detail')).toBeTruthy();
    expect(getByLabelText('world.art_marker_atlas')).toBeTruthy();
    expect(getByLabelText('world.art_terrain_atlas')).toBeTruthy();
  });
});

describe('a plot shows the building, not a glyph', () => {
  it('has artwork for every catalogued building at every playable level', () => {
    const buildingCodes = [
      'palace',
      'farm',
      'lumber_mill',
      'quarry',
      'warehouse',
      'barracks',
      'archery_range',
      'stable',
      'siege_workshop',
      'academy',
      'embassy',
      'marketplace',
      'hospital',
      'walls',
      'watchtower',
      'iron_mine',
      'treasury',
      'tavern',
    ];

    for (const code of buildingCodes) {
      for (const level of [1, 2, 3]) {
        expect(getBuildingAsset(code, level)).not.toBeNull();
      }
    }
  });

  it('renders the generated artwork for a building that has it', () => {
    const { getByLabelText } = render(
      <CitySlot
        slot={plot({ code: 'palace', level: 1, name_key: 'buildings.palace', category: 'core' } as CitySlotData['building'])}
        size={96}
        isBuilding={false}
        constructionFinishTimestamp={null}
        onPress={jest.fn()}
      />,
    );

    expect(
      getByLabelText('city.building_image_accessibility {"building":"buildings.palace"}'),
    ).toBeTruthy();
  });

  it('falls back to the category glyph for a building with no rendered level', () => {
    const { queryByLabelText } = render(
      <CitySlot
        slot={plot({ code: 'tavern', level: 9, name_key: 'buildings.tavern', category: 'economy' } as CitySlotData['building'])}
        size={96}
        isBuilding={false}
        constructionFinishTimestamp={null}
        onPress={jest.fn()}
      />,
    );

    // No artwork exists for tavern level 9, and the plot must still render.
    expect(
      queryByLabelText('city.building_image_accessibility {"building":"buildings.tavern"}'),
    ).toBeNull();
  });

  it('keeps the plot reachable by its own accessible name', () => {
    const { getByLabelText } = render(
      <CitySlot
        slot={plot(null)}
        size={96}
        isBuilding={false}
        constructionFinishTimestamp={null}
        onPress={jest.fn()}
      />,
    );

    expect(getByLabelText('city.slot_accessible_empty')).toBeTruthy();
  });
});
