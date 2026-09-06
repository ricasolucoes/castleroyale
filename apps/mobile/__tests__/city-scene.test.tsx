import React from 'react';
import { configure, fireEvent, render } from '@testing-library/react-native';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import type { CityData } from '@castleroyale/contracts';
import { MIN_TOUCH_TARGET } from '@castleroyale/tooling/design-tokens';

import { CityScene } from '../src/features/city/components/CityScene';
import { useCitySelectionStore } from '../src/features/city/state/citySelectionStore';

// The mocked BottomSheet always renders with `accessibilityViewIsModal`
// regardless of `index` (it does not simulate open/close visibility), which
// makes React Native Testing Library treat every OTHER sibling in the tree
// (the whole scene grid) as hidden-behind-a-modal by default. The real
// gorhom sheet does not have this artifact; this is a mock-only interaction,
// so queries opt back into the full tree.
configure({ defaultIncludeHiddenElements: true });

// Mock translation — echoes the key plus JSON params, exactly like
// world-tile-detail-sheet.test.tsx.
jest.mock('../src/i18n/useTranslation', () => ({
  useTranslation: () => ({
    t: (key: string, params?: Record<string, string | number>) => {
      if (params) {
        return `${key} ${JSON.stringify(params)}`;
      }
      return key;
    },
  }),
}));

// Mock bottom sheet — a forwardRef View, exactly like world-tile-detail-sheet.test.tsx.
jest.mock('@gorhom/bottom-sheet', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactMock = require('react');
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const BottomSheetMock = ReactMock.forwardRef((props: any, _ref: any) => (
    <ReactNativeMock.View testID="gorhom-bottom-sheet" {...props} />
  ));
  return {
    __esModule: true,
    default: BottomSheetMock,
  };
});

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }),
}));

// `@expo/vector-icons` pulls in `expo-font` -> `expo-asset`, which is not
// resolvable from Jest's plain Node resolution in this workspace (a pre-existing
// hoisting gap, not something this plan's code introduces). Mock it the same
// way `@gorhom/bottom-sheet` is mocked project-wide: a trivial View standing
// in for a native-backed component the test does not need to render for real.
jest.mock('@expo/vector-icons', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  return {
    MaterialCommunityIcons: (props: { name: string }) => (
      <ReactNativeMock.View testID={`icon-${props.name}`} />
    ),
  };
});

function buildCity(): CityData {
  const occupied: Record<string, { code: string; category: string }> = {
    plot_01: { code: 'palace', category: 'core' },
    plot_02: { code: 'farm', category: 'economy' },
    plot_03: { code: 'lumber_mill', category: 'economy' },
    plot_04: { code: 'quarry', category: 'economy' },
    plot_05: { code: 'warehouse', category: 'economy' },
  };

  const slots = Array.from({ length: 18 }, (_, index) => {
    const slot = `plot_${String(index + 1).padStart(2, '0')}`;
    const occupant = occupied[slot];

    if (!occupant) {
      return { slot, status: 'empty' as const, building: null };
    }

    return {
      slot,
      status: 'occupied' as const,
      building: {
        slot,
        code: occupant.code,
        name_key: `buildings.${occupant.code}`,
        category: occupant.category,
        level: 1,
        max_level: 10,
        next_level_cost: { food: 0, wood: 0, stone: 0, iron: 0, gold: 0 },
        build_time_seconds: 60,
      },
    };
  });

  return {
    player: { id: 'player-1', name: 'Governor', world_id: 'world-1' },
    world: { id: 'world-1', code: 'W1', name: 'World One' },
    city: { id: 'city-1', name_key: 'city.starter_name', x: 10, y: 20 },
    resources: {
      current: { food: 100, wood: 100, stone: 100, iron: 100, gold: 100 },
      capacity: { food: 1000, wood: 1000, stone: 1000, iron: 1000, gold: 1000 },
      rate: { food: 3600, wood: 3600, stone: 3600, iron: 0, gold: 0 },
    },
    slots,
    constructions: [],
    queue_limit: 4,
    realtime: { key: 'k', host: 'realtime.test', port: 443, scheme: 'https', auth_endpoint: 'https://realtime.test/auth' },
    server_time: new Date().toISOString(),
  } as unknown as CityData;
}

describe('CityScene', () => {
  beforeEach(() => {
    useCitySelectionStore.setState({ selectedSlot: null });
  });

  it('renders exactly one accessible button per server slot, none hardcoded', () => {
    const city = buildCity();
    const { getByLabelText, getAllByRole } = render(
      <CityScene city={city} isRefreshing={false} onRefresh={jest.fn()} />,
    );

    const sceneFrame = getByLabelText('city.scene_accessibility');
    fireEvent(sceneFrame, 'layout', {
      nativeEvent: { layout: { width: 390, height: 600, x: 0, y: 0 } },
    });

    const buttons = getAllByRole('button');
    expect(buttons).toHaveLength(city.slots.length);
    expect(buttons).toHaveLength(18);
  });

  it('keeps every plot at or above the 44pt touch target regardless of visual size', () => {
    const city = buildCity();
    const { getByLabelText, getAllByRole } = render(
      <CityScene city={city} isRefreshing={false} onRefresh={jest.fn()} />,
    );

    const sceneFrame = getByLabelText('city.scene_accessibility');
    fireEvent(sceneFrame, 'layout', {
      nativeEvent: { layout: { width: 390, height: 600, x: 0, y: 0 } },
    });

    for (const button of getAllByRole('button')) {
      const style = Array.isArray(button.props.style)
        ? Object.assign({}, ...button.props.style.flat(Infinity))
        : button.props.style;
      const hitSlop = typeof button.props.hitSlop === 'number' ? button.props.hitSlop : 0;
      expect((style.width ?? 0) + hitSlop * 2).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);
    }
  });

  it('opens the sheet with the empty-plot copy when an empty plot is pressed', () => {
    const city = buildCity();
    const { getByLabelText, getAllByRole, getByText } = render(
      <CityScene city={city} isRefreshing={false} onRefresh={jest.fn()} />,
    );

    const sceneFrame = getByLabelText('city.scene_accessibility');
    fireEvent(sceneFrame, 'layout', {
      nativeEvent: { layout: { width: 390, height: 600, x: 0, y: 0 } },
    });

    const emptyPlotIndex = city.slots.findIndex((slot) => slot.slot === 'plot_07');
    fireEvent.press(getAllByRole('button')[emptyPlotIndex]!);

    expect(useCitySelectionStore.getState().selectedSlot).toBe('plot_07');
    expect(getByText('city.slot_empty')).toBeTruthy();
  });

  it('opens the sheet with building details and no upgrade CTA when an occupied plot is pressed', () => {
    const city = buildCity();
    const { getByLabelText, getAllByRole, getByText, queryByText } = render(
      <CityScene city={city} isRefreshing={false} onRefresh={jest.fn()} />,
    );

    const sceneFrame = getByLabelText('city.scene_accessibility');
    fireEvent(sceneFrame, 'layout', {
      nativeEvent: { layout: { width: 390, height: 600, x: 0, y: 0 } },
    });

    const palaceIndex = city.slots.findIndex((slot) => slot.slot === 'plot_01');
    fireEvent.press(getAllByRole('button')[palaceIndex]!);

    expect(useCitySelectionStore.getState().selectedSlot).toBe('plot_01');
    expect(getByText('building.level {"level":1}')).toBeTruthy();
    expect(queryByText('building.upgrade')).toBeNull();
  });

  it('ticks a timer on every plot with an open order, not just the soonest', () => {
    const now = Date.now();
    const city = buildCity();
    city.server_time = new Date(now).toISOString();
    city.constructions = [
      {
        id: 'construction-farm',
        building_code: 'farm',
        from_level: 1,
        target_level: 2,
        started_at: new Date(now - 10_000).toISOString(),
        finishes_at: new Date(now + 30_000).toISOString(),
      },
      {
        id: 'construction-quarry',
        building_code: 'quarry',
        from_level: 1,
        target_level: 2,
        started_at: new Date(now - 10_000).toISOString(),
        finishes_at: new Date(now + 90_000).toISOString(),
      },
    ];

    const { getByLabelText, getAllByText } = render(
      <CityScene city={city} isRefreshing={false} onRefresh={jest.fn()} />,
    );

    const sceneFrame = getByLabelText('city.scene_accessibility');
    fireEvent(sceneFrame, 'layout', {
      nativeEvent: { layout: { width: 390, height: 600, x: 0, y: 0 } },
    });

    expect(getAllByText(/^\d{2}:\d{2}:\d{2}$/)).toHaveLength(2);
  });
});

describe('city tab screen architecture', () => {
  it('renders the CityScene and never the retired card list, mutation or upgrade route', () => {
    const source = readFileSync(join(__dirname, '../app/(tabs)/city.tsx'), 'utf8');

    expect(source).toContain('CityScene');
    expect(source).not.toContain('city.buildings');
    expect(source).not.toContain('<Card');
    expect(source).not.toContain('useMutation');
    expect(source).not.toContain('/upgrade');
  });

  it('leaves the top safe area to the resource bar and drops the duplicated resource row', () => {
    const scene = readFileSync(join(__dirname, '../src/features/city/components/CityScene.tsx'), 'utf8');
    expect(scene).not.toContain('insets.top');
    expect(scene).not.toContain('ResourceCounter');

    const layout = readFileSync(join(__dirname, '../app/(tabs)/_layout.tsx'), 'utf8');
    expect(layout).toContain('<ResourceBar />');
    expect(layout.indexOf('<ResourceBar />')).toBeLessThan(layout.indexOf('<Tabs'));

    const bar = readFileSync(join(__dirname, '../src/features/economy/components/ResourceBar.tsx'), 'utf8');
    expect(bar).toContain('insets.top');
    expect(bar).not.toMatch(/height:\s*\d/);
  });

  it('refetches on app resume rather than extrapolating across a backgrounding gap', () => {
    const root = readFileSync(join(__dirname, '../app/_layout.tsx'), 'utf8');
    expect(root).toContain('focusManager.setEventListener');
    expect(root).toContain('AppState');
  });
});
