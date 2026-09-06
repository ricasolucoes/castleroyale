import React from 'react';
import { configure, fireEvent, render } from '@testing-library/react-native';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import type { CitySlot, Construction, ResourceBundle } from '@castleroyale/contracts';

import { ApiError } from '../src/api/client';
import { CitySlotDetailSheet } from '../src/features/city/components/CitySlotDetailSheet';

// Same mock artifact as city-scene.test.tsx: the stand-in BottomSheet always
// carries `accessibilityViewIsModal`, so queries must opt back into the tree.
configure({ defaultIncludeHiddenElements: true });

jest.mock('../src/i18n/useTranslation', () => ({
  useTranslation: () => ({
    t: (key: string, params?: Record<string, string | number>) =>
      params ? `${key} ${JSON.stringify(params)}` : key,
  }),
}));

jest.mock('@gorhom/bottom-sheet', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactMock = require('react');
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const BottomSheetMock = ReactMock.forwardRef((props: any, _ref: any) => (
    <ReactNativeMock.View testID="gorhom-bottom-sheet" {...props} />
  ));
  return { __esModule: true, default: BottomSheetMock };
});

jest.mock('@expo/vector-icons', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  return {
    MaterialCommunityIcons: (props: { name: string }) => (
      <ReactNativeMock.View testID={`icon-${props.name}`} />
    ),
  };
});

// The mutation is stubbed per test so its state (idle / pending / errored) is
// controllable without a QueryClientProvider or a network layer.
const mockMutate = jest.fn();
let mockUpgradeState: { mutate: jest.Mock; isPending: boolean; error: unknown } = {
  mutate: mockMutate,
  isPending: false,
  error: null,
};

jest.mock('../src/features/city/api/useUpgradeBuilding', () => ({
  useUpgradeBuilding: () => mockUpgradeState,
}));

function buildSlot(overrides: Partial<NonNullable<CitySlot['building']>> = {}): CitySlot {
  return {
    slot: 'plot_02',
    status: 'occupied',
    building: {
      slot: 'plot_02',
      code: 'farm',
      name_key: 'buildings.farm',
      category: 'economy',
      level: 1,
      max_level: 3,
      next_level_cost: { food: 0, wood: 120, stone: 60, iron: 0, gold: 0 },
      build_time_seconds: 20,
      ...overrides,
    },
  } as unknown as CitySlot;
}

const AFFORDABLE: ResourceBundle = {
  food: 500,
  wood: 500,
  stone: 500,
  iron: 0,
  gold: 0,
} as unknown as ResourceBundle;

function renderSheet(props: {
  slot?: CitySlot;
  construction?: Construction | null;
  resources?: ResourceBundle;
  activeConstructions?: number;
  queueLimit?: number;
}) {
  return render(
    <CitySlotDetailSheet
      slot={props.slot ?? buildSlot()}
      construction={props.construction ?? null}
      serverTime={new Date().toISOString()}
      resources={props.resources ?? AFFORDABLE}
      activeConstructions={props.activeConstructions ?? 0}
      queueLimit={props.queueLimit ?? 4}
      open
      onClose={jest.fn()}
      onConstructionFinish={jest.fn()}
    />,
  );
}

describe('building upgrade CTA', () => {
  beforeEach(() => {
    mockMutate.mockClear();
    mockUpgradeState = { mutate: mockMutate, isPending: false, error: null };
  });

  it('offers the upgrade when the server snapshot covers the cost', () => {
    const { getByText } = renderSheet({});

    expect(getByText('building.upgrade')).toBeTruthy();
    fireEvent.press(getByText('building.upgrade'));
    expect(mockMutate).toHaveBeenCalledWith('farm');
  });

  it('refuses on the snapshot, not the ticking bar', () => {
    const short = { ...AFFORDABLE, wood: 100 } as unknown as ResourceBundle;
    const { getByText, queryByText, rerender } = renderSheet({ resources: short });

    expect(getByText('building.cannot_afford')).toBeTruthy();
    expect(queryByText('building.upgrade')).toBeNull();
    fireEvent.press(getByText('building.cannot_afford'));
    expect(mockMutate).not.toHaveBeenCalled();

    // Capacity and rate are what the resource bar interpolates between polls.
    // Neither may reach the affordability decision — only `current` may.
    rerender(
      <CitySlotDetailSheet
        slot={buildSlot()}
        construction={null}
        serverTime={new Date().toISOString()}
        resources={short}
        activeConstructions={0}
        queueLimit={4}
        open
        onClose={jest.fn()}
        onConstructionFinish={jest.fn()}
      />,
    );

    expect(getByText('building.cannot_afford')).toBeTruthy();
    expect(queryByText('building.upgrade')).toBeNull();
  });

  it('says the queue is full before it says anything else about affordability', () => {
    const { getByText, queryByText } = renderSheet({
      activeConstructions: 4,
      queueLimit: 4,
    });

    expect(getByText('building.queue_full')).toBeTruthy();
    expect(queryByText('building.upgrade')).toBeNull();
    expect(queryByText('building.cannot_afford')).toBeNull();
  });

  it('shows only a max-level badge at max level', () => {
    const { getByText, queryByText, queryAllByRole } = renderSheet({
      slot: buildSlot({ level: 3, max_level: 3 }),
    });

    expect(getByText('building.max_level')).toBeTruthy();
    expect(queryByText('building.upgrade')).toBeNull();
    expect(queryByText('building.cannot_afford')).toBeNull();
    expect(queryByText('building.queue_full')).toBeNull();
    expect(queryAllByRole('button')).toHaveLength(0);
  });

  it('shows the timer and no button while this building is under construction', () => {
    const now = Date.now();
    const { getByText, queryByText } = renderSheet({
      construction: {
        id: 'order-1',
        building_code: 'farm',
        target_level: 2,
        started_at: new Date(now - 5_000).toISOString(),
        finishes_at: new Date(now + 20_000).toISOString(),
      } as unknown as Construction,
    });

    expect(queryByText('building.upgrade')).toBeNull();
    expect(getByText(/^\d{2}:\d{2}:\d{2}$/)).toBeTruthy();
  });

  it('swaps the label while the mutation is in flight', () => {
    mockUpgradeState = { mutate: mockMutate, isPending: true, error: null };
    const { getByText, queryByText } = renderSheet({});

    expect(getByText('building.upgrading')).toBeTruthy();
    expect(queryByText('building.upgrade')).toBeNull();

    fireEvent.press(getByText('building.upgrading'));
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it('renders localized copy for a server refusal and keeps the sheet open', () => {
    mockUpgradeState = {
      mutate: mockMutate,
      isPending: false,
      error: new ApiError(400, {
        error: { code: 'BUILD_QUEUE_FULL', message: 'x', retryable: false },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
      } as any),
    };

    const { getByText } = renderSheet({});

    expect(getByText('errors.BUILD_QUEUE_FULL')).toBeTruthy();
    // The sheet did not dismiss — the building it describes is still on screen.
    expect(getByText('buildings.farm')).toBeTruthy();
  });

  it('falls back to generic copy for a non-API failure', () => {
    mockUpgradeState = { mutate: mockMutate, isPending: false, error: new Error('offline') };

    const { getByText } = renderSheet({});

    expect(getByText('errors.generic')).toBeTruthy();
  });

  it('never colours a normal ceiling as danger', () => {
    const source = readFileSync(
      join(__dirname, '../src/features/city/components/CitySlotDetailSheet.tsx'),
      'utf8',
    );

    // Losing a race against another device, or simply not having saved up yet,
    // is not destructive. Red is reserved for destruction.
    expect(source).not.toContain('theme.color.danger');
    expect(source).not.toContain('variant="danger"');
    // Affordability is decided on the server snapshot alone — the interpolated
    // bar must never reach this decision.
    expect(source).not.toContain('interpolateResources');
  });
});
