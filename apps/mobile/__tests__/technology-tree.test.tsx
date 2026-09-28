import React from 'react';
import { configure, fireEvent, render } from '@testing-library/react-native';
import type {
  ActiveResearch,
  CitySlot,
  Technology,
  TechnologyTreeData,
} from '@castleroyale/contracts';

import TechnologyTreeScreen from '../app/technology';
import { CitySlotDetailSheet } from '../src/features/city/components/CitySlotDetailSheet';
import { CATEGORY_ORDER } from '../src/features/technology/components/CategoryJumpStrip';
import { useTechnologySelectionStore } from '../src/features/technology/state/technologySelectionStore';

// Same mock artifact as city-scene.test.tsx and building-upgrade.test.tsx: the
// stand-in BottomSheet always carries `accessibilityViewIsModal`, so queries
// must opt back into the tree.
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

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 }),
}));

// `mock`-prefixed per the Jest hoisting rule a factory must respect.
let mockTechnologyQueryState: {
  data: TechnologyTreeData | undefined;
  isPending: boolean;
  error: unknown;
  refetch: jest.Mock;
} = {
  data: undefined,
  isPending: true,
  error: null,
  refetch: jest.fn(),
};

jest.mock('../src/features/technology/api/useTechnologyQuery', () => ({
  useTechnologyQuery: () => mockTechnologyQueryState,
}));

jest.mock('../src/features/city/api/useCityQuery', () => ({
  useCityQuery: () => ({
    data: {
      resources: {
        current: { food: 500, wood: 500, stone: 500, iron: 500, gold: 500 },
      },
    },
  }),
}));

jest.mock('../src/features/technology/api/useResearchTechnology', () => ({
  useResearchTechnology: () => ({ mutate: jest.fn(), isPending: false, error: null }),
}));

jest.mock('../src/features/city/api/useUpgradeBuilding', () => ({
  useUpgradeBuilding: () => ({ mutate: jest.fn(), isPending: false, error: null }),
}));

function buildTechnology(overrides: Partial<Technology> = {}): Technology {
  const code = overrides.code ?? 'agriculture';

  return {
    code,
    name_key: `technologies.${code}`,
    description_key: `technologies.${code}_desc`,
    category: 'economy',
    tier: 0,
    prerequisites: [],
    level: 0,
    max_level: 3,
    state: 'available',
    next_level: {
      cost: { food: 0, wood: 100, stone: 0, iron: 0, gold: 0 },
      research_time_seconds: 30,
      effects: [],
    },
    ...overrides,
  } as unknown as Technology;
}

function buildTreeData(): TechnologyTreeData {
  const now = Date.now();

  const technologies: Technology[] = [
    buildTechnology({
      code: 'economy_tech',
      category: 'economy',
      state: 'locked',
      prerequisites: [{ code: 'gate', level: 1 }],
    }),
    buildTechnology({ code: 'military_tech', category: 'military', state: 'available' }),
    buildTechnology({ code: 'defense_tech', category: 'defense', state: 'in_progress' }),
    buildTechnology({
      code: 'logistics_tech',
      category: 'logistics',
      state: 'completed',
      level: 3,
      max_level: 3,
    }),
    buildTechnology({ code: 'construction_tech', category: 'construction', state: 'available' }),
    buildTechnology({ code: 'exploration_tech', category: 'exploration', state: 'available' }),
    buildTechnology({ code: 'alliance_tech', category: 'alliance', state: 'available' }),
    buildTechnology({ code: 'siege_tech', category: 'siege', state: 'available' }),
  ];

  const research: ActiveResearch = {
    technology_code: 'defense_tech',
    target_level: 1,
    started_at: new Date(now - 5_000).toISOString(),
    finishes_at: new Date(now + 20_000).toISOString(),
  } as unknown as ActiveResearch;

  return {
    technologies,
    research,
    server_time: new Date(now).toISOString(),
  } as unknown as TechnologyTreeData;
}

describe('TechnologyTreeScreen', () => {
  beforeEach(() => {
    useTechnologySelectionStore.setState({ selectedTechnology: null });
    mockTechnologyQueryState = {
      data: buildTreeData(),
      isPending: false,
      error: null,
      refetch: jest.fn(),
    };
  });

  it('renders every category section', () => {
    const { getAllByText } = render(<TechnologyTreeScreen />);

    for (const category of CATEGORY_ORDER) {
      // Once in the jump strip, once as the section header.
      expect(getAllByText(`technology.category_${category}`).length).toBeGreaterThanOrEqual(2);
    }
  });

  it('renders the four node states distinctly', () => {
    const { getAllByTestId, getAllByText } = render(<TechnologyTreeScreen />);

    expect(getAllByTestId('icon-lock-outline')).toHaveLength(1);
    expect(getAllByTestId('icon-progress-clock')).toHaveLength(1);
    expect(getAllByTestId('icon-check-circle')).toHaveLength(1);
    expect(getAllByText(/^\d{2}:\d{2}:\d{2}$/)).toHaveLength(1);
  });

  it('gives every jump chip a 44pt touch target', () => {
    const { getAllByText } = render(<TechnologyTreeScreen />);

    for (const category of CATEGORY_ORDER) {
      // The jump strip renders before the category sections, so the first of
      // the two matches (chip label, then section header label) is the chip.
      const [chipLabel] = getAllByText(`technology.category_${category}`);

      // Walk up from the label to the nearest `accessibilityRole="button"`
      // ancestor — the Pressable itself — and read its resolved style.
      let chip = chipLabel ?? null;
      while (chip && chip.props?.['accessibilityRole'] !== 'button') chip = chip.parent;

      const style = Array.isArray(chip?.props['style'])
        ? Object.assign({}, ...chip!.props['style'].flat(Infinity))
        : chip?.props['style'];
      expect(style.minHeight).toBeGreaterThanOrEqual(44);
    }
  });

  it('opens the detail sheet for the tapped technology', () => {
    const { getByText } = render(<TechnologyTreeScreen />);

    fireEvent.press(getByText('technologies.military_tech'));

    expect(useTechnologySelectionStore.getState().selectedTechnology).toBe('military_tech');
  });
});

describe('the Academy entry point', () => {
  function buildAcademySlot(): CitySlot {
    return {
      slot: 'plot_06',
      status: 'occupied',
      building: {
        slot: 'plot_06',
        code: 'academy',
        name_key: 'buildings.academy',
        category: 'core',
        level: 1,
        max_level: 5,
        next_level_cost: { food: 0, wood: 100, stone: 0, iron: 0, gold: 0 },
        build_time_seconds: 30,
      },
    } as unknown as CitySlot;
  }

  function buildFarmSlot(): CitySlot {
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
      },
    } as unknown as CitySlot;
  }

  const AFFORDABLE = { food: 500, wood: 500, stone: 500, iron: 500, gold: 500 };

  it('offers Open Research only on the Academy', () => {
    const { getByText } = render(
      <CitySlotDetailSheet
        slot={buildAcademySlot()}
        construction={null}
        serverTime={new Date().toISOString()}
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        resources={AFFORDABLE as any}
        activeConstructions={0}
        queueLimit={4}
        open
        onClose={jest.fn()}
        onConstructionFinish={jest.fn()}
      />,
    );

    expect(getByText('city.open_research')).toBeTruthy();
  });

  it('does not offer Open Research on a non-Academy building', () => {
    const { queryByText } = render(
      <CitySlotDetailSheet
        slot={buildFarmSlot()}
        construction={null}
        serverTime={new Date().toISOString()}
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        resources={AFFORDABLE as any}
        activeConstructions={0}
        queueLimit={4}
        open
        onClose={jest.fn()}
        onConstructionFinish={jest.fn()}
      />,
    );

    expect(queryByText('city.open_research')).toBeNull();
  });
});
