import React from 'react';
import { act, render } from '@testing-library/react-native';
import type { CityData } from '@castleroyale/contracts';

import { ResourceBar } from '../src/features/economy/components/ResourceBar';
import { useCityQuery } from '../src/features/city/api/useCityQuery';

jest.mock('../src/features/city/api/useCityQuery', () => ({
  useCityQuery: jest.fn(),
}));

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

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }),
}));

// `@expo/vector-icons` pulls in `expo-font` -> `expo-asset`, unresolvable from
// Jest's plain Node resolution in this workspace — the same mock used by
// city-scene.test.tsx.
jest.mock('@expo/vector-icons', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  return {
    MaterialCommunityIcons: (props: { name: string }) => (
      <ReactNativeMock.View testID={`icon-${props.name}`} />
    ),
  };
});

// Skeleton is a first-party, third-party-free component with no testID
// pass-through, so a trivial stand-in lets the "first load" test count pills
// without depending on its internal Animated.View structure.
jest.mock('../src/shared/components/Skeleton', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  return {
    Skeleton: () => <ReactNativeMock.View testID="skeleton-pill" />,
  };
});

const mockedUseCityQuery = useCityQuery as jest.MockedFunction<typeof useCityQuery>;

function buildCityData(overrides: Partial<CityData['resources']> = {}): CityData {
  return {
    player: { id: 'player-1', name: 'Governor', world_id: 'world-1' },
    world: { id: 'world-1', code: 'W1', name: 'World One' },
    city: { id: 'city-1', name_key: 'city.starter_name', x: 10, y: 20 },
    resources: {
      current: { food: 100, wood: 200, stone: 300, iron: 400, gold: 500 },
      capacity: { food: 1000, wood: 1000, stone: 1000, iron: 1000, gold: 1000 },
      rate: { food: 3600, wood: 3600, stone: 3600, iron: 3600, gold: 3600 },
      ...overrides,
    },
    slots: [],
    construction: null,
    realtime: {
      key: 'k',
      host: 'realtime.test',
      port: 443,
      scheme: 'https',
      auth_endpoint: 'https://realtime.test/auth',
    },
    server_time: new Date(0).toISOString(),
  } as unknown as CityData;
}

function mockQueryResult(overrides: Record<string, unknown>) {
  mockedUseCityQuery.mockReturnValue({
    data: undefined,
    dataUpdatedAt: 0,
    isPending: false,
    isError: false,
    isSuccess: false,
    ...overrides,
  } as unknown as ReturnType<typeof useCityQuery>);
}

function flattenStyle(style: unknown): Record<string, unknown> {
  return Array.isArray(style) ? Object.assign({}, ...style.flat(Infinity)) : (style as Record<string, unknown>);
}

describe('ResourceBar', () => {
  afterEach(() => {
    jest.useRealTimers();
    jest.clearAllMocks();
  });

  it('renders exactly five icon-bearing cells from successful data', () => {
    jest.useFakeTimers();
    jest.setSystemTime(0);
    mockQueryResult({ data: buildCityData(), dataUpdatedAt: 0, isSuccess: true });

    const { getByTestId } = render(<ResourceBar />);

    expect(getByTestId('icon-barley')).toBeTruthy();
    expect(getByTestId('icon-tree')).toBeTruthy();
    expect(getByTestId('icon-terrain')).toBeTruthy();
    expect(getByTestId('icon-anvil')).toBeTruthy();
    expect(getByTestId('icon-gold')).toBeTruthy();
  });

  it('renders five skeleton pills and no numerals while pending with no cached data', () => {
    mockQueryResult({ isPending: true });

    const { getAllByTestId, queryByTestId } = render(<ResourceBar />);

    expect(getAllByTestId('skeleton-pill')).toHaveLength(5);
    expect(queryByTestId('icon-barley')).toBeNull();
  });

  it('renders nothing when the query has never succeeded', () => {
    mockQueryResult({ isPending: false, isError: true, data: undefined });

    const { toJSON } = render(<ResourceBar />);

    expect(toJSON()).toBeNull();
  });

  it('signals a full warehouse with the tray-alert glyph and the MAX caption, numeral colour unchanged', () => {
    jest.useFakeTimers();
    jest.setSystemTime(0);
    mockQueryResult({
      data: buildCityData({ current: { food: 1000 }, capacity: { food: 1000 } }),
      dataUpdatedAt: 0,
      isSuccess: true,
    });

    const { getByTestId, getByText } = render(<ResourceBar />);

    expect(getByTestId('icon-tray-alert')).toBeTruthy();
    expect(getByText('resources.storage_full_short')).toBeTruthy();
  });

  it('ticks the displayed amount forward by one second of production between reads', () => {
    jest.useFakeTimers();
    jest.setSystemTime(0);
    mockQueryResult({
      data: buildCityData({ rate: { food: 3600, wood: 0, stone: 0, iron: 0, gold: 0 } }),
      dataUpdatedAt: 0,
      isSuccess: true,
    });

    const { getByText } = render(<ResourceBar />);
    expect(getByText('100')).toBeTruthy();

    act(() => {
      jest.advanceTimersByTime(1000);
    });

    expect(getByText('101')).toBeTruthy();
  });

  it('freezes the display and dims to 0.6 opacity when a refetch fails with data cached', () => {
    jest.useFakeTimers();
    jest.setSystemTime(0);
    mockQueryResult({
      data: buildCityData({ rate: { food: 3600, wood: 0, stone: 0, iron: 0, gold: 0 } }),
      dataUpdatedAt: 0,
      isError: true,
    });

    const { getByText, getByLabelText } = render(<ResourceBar />);

    act(() => {
      jest.advanceTimersByTime(5000);
    });

    // No interval was ever created, so the amount from the moment of capture
    // stands even though five seconds of fake time have elapsed.
    expect(getByText('100')).toBeTruthy();

    const container = getByLabelText('resources.bar_accessibility');
    expect(flattenStyle(container.props.style)['opacity']).toBe(0.6);
  });

  it('builds each cell accessibility label from accessible_reading/accessible_full and never a button role', () => {
    jest.useFakeTimers();
    jest.setSystemTime(0);
    mockQueryResult({
      data: buildCityData({ current: { food: 100 }, capacity: { food: 1000 } }),
      dataUpdatedAt: 0,
      isSuccess: true,
    });

    const { getByLabelText, queryAllByRole } = render(<ResourceBar />);

    expect(
      getByLabelText('resources.accessible_reading {"resource":"resources.food","amount":100,"capacity":1000}'),
    ).toBeTruthy();
    expect(queryAllByRole('button')).toHaveLength(0);
  });

  it('carries the bar-level accessibility landmark from resources.bar_accessibility', () => {
    jest.useFakeTimers();
    jest.setSystemTime(0);
    mockQueryResult({ data: buildCityData(), dataUpdatedAt: 0, isSuccess: true });

    const { getByLabelText } = render(<ResourceBar />);

    expect(getByLabelText('resources.bar_accessibility')).toBeTruthy();
  });
});
