import React from 'react';
import { configure, fireEvent, render } from '@testing-library/react-native';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import type { ActiveResearch, ResourceBundle, Technology } from '@castleroyale/contracts';

import { ApiError } from '../src/api/client';
import { TechnologyDetailSheet } from '../src/features/technology/components/TechnologyDetailSheet';

// Same mock artifact as building-upgrade.test.tsx: the stand-in BottomSheet
// always carries `accessibilityViewIsModal`, so queries must opt back into
// the tree.
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
// controllable without a QueryClientProvider or a network layer. Variable
// names are `mock`-prefixed — a jest.mock factory may not close over a
// non-`mock`-prefixed variable (the Jest hoisting rule that cost time in 09-05).
const mockMutate = jest.fn();
let mockResearchState: { mutate: jest.Mock; isPending: boolean; error: unknown } = {
  mutate: mockMutate,
  isPending: false,
  error: null,
};

jest.mock('../src/features/technology/api/useResearchTechnology', () => ({
  useResearchTechnology: () => mockResearchState,
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
      cost: { food: 0, wood: 100, stone: 50, iron: 0, gold: 0 },
      research_time_seconds: 30,
      effects: [{ target: 'production.food', operation: 'multiply', value: 1100 }],
    },
    ...overrides,
  } as unknown as Technology;
}

const AFFORDABLE: ResourceBundle = {
  food: 500,
  wood: 500,
  stone: 500,
  iron: 500,
  gold: 500,
} as unknown as ResourceBundle;

function renderSheet(props: {
  technology?: Technology | null;
  technologyByCode?: Map<string, Technology>;
  activeResearch?: ActiveResearch | null;
  resources?: ResourceBundle;
}) {
  const technology = props.technology === undefined ? buildTechnology() : props.technology;
  const technologyByCode =
    props.technologyByCode ?? (technology ? new Map([[technology.code, technology]]) : new Map());

  return render(
    <TechnologyDetailSheet
      technology={technology}
      technologyByCode={technologyByCode}
      activeResearch={props.activeResearch ?? null}
      serverTime={new Date().toISOString()}
      resources={props.resources ?? AFFORDABLE}
      open
      onClose={jest.fn()}
    />,
  );
}

describe('technology research CTA', () => {
  beforeEach(() => {
    mockMutate.mockClear();
    mockResearchState = { mutate: mockMutate, isPending: false, error: null };
  });

  it('offers research when the server snapshot covers the cost', () => {
    const { getByText } = renderSheet({});

    expect(getByText('technology.research')).toBeTruthy();
    fireEvent.press(getByText('technology.research'));
    expect(mockMutate).toHaveBeenCalledWith('agriculture');
  });

  it('shows only a max-level badge at max level', () => {
    const { getByText, queryByText, queryAllByRole } = renderSheet({
      technology: buildTechnology({ level: 3, max_level: 3, state: 'completed' }),
    });

    expect(getByText('technology.max_level')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();
    expect(queryByText('technology.locked')).toBeNull();
    expect(queryByText('technology.busy')).toBeNull();
    expect(queryByText('technology.cannot_afford')).toBeNull();
    expect(queryAllByRole('button')).toHaveLength(0);
  });

  it('shows the timer and no button while this is the active research', () => {
    const now = Date.now();
    const technology = buildTechnology({ level: 0, state: 'in_progress' });
    const { getByText, queryByText } = renderSheet({
      technology,
      activeResearch: {
        technology_code: 'agriculture',
        target_level: 1,
        started_at: new Date(now - 5_000).toISOString(),
        finishes_at: new Date(now + 20_000).toISOString(),
      } as unknown as ActiveResearch,
    });

    expect(queryByText('technology.research')).toBeNull();
    expect(getByText(/^\d{2}:\d{2}:\d{2}$/)).toBeTruthy();
  });

  it('shows a disabled locked button when a prerequisite is unmet', () => {
    const prerequisite = buildTechnology({ code: 'masonry', category: 'construction', level: 0 });
    const technology = buildTechnology({
      code: 'siege_engineering',
      category: 'siege',
      prerequisites: [{ code: 'masonry', level: 1 }],
    });
    const { getByText, queryByText } = renderSheet({
      technology,
      technologyByCode: new Map([
        [technology.code, technology],
        [prerequisite.code, prerequisite],
      ]),
    });

    expect(getByText('technology.locked')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();
  });

  it('shows a disabled busy button when another research is running', () => {
    const { getByText, queryByText } = renderSheet({
      activeResearch: {
        technology_code: 'mining',
        target_level: 1,
        started_at: new Date().toISOString(),
        finishes_at: new Date(Date.now() + 10_000).toISOString(),
      } as unknown as ActiveResearch,
    });

    expect(getByText('technology.busy')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();
  });

  it('shows a disabled cannot-afford button when the snapshot falls short', () => {
    const short = { ...AFFORDABLE, wood: 10 } as unknown as ResourceBundle;
    const { getByText, queryByText } = renderSheet({ resources: short });

    expect(getByText('technology.cannot_afford')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();
  });

  it('refuses on the snapshot, not the ticking bar', () => {
    const short = { ...AFFORDABLE, wood: 10 } as unknown as ResourceBundle;
    const technology = buildTechnology();
    const { getByText, queryByText, rerender } = renderSheet({ technology, resources: short });

    expect(getByText('technology.cannot_afford')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();

    // Nothing in this component's props represents capacity or rate — only
    // `resources.current` (the last server snapshot) ever reaches this
    // decision. Re-rendering with the identical snapshot must not flip it.
    rerender(
      <TechnologyDetailSheet
        technology={technology}
        technologyByCode={new Map([[technology.code, technology]])}
        activeResearch={null}
        serverTime={new Date().toISOString()}
        resources={short}
        open
        onClose={jest.fn()}
      />,
    );

    expect(getByText('technology.cannot_afford')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();
  });

  it('says locked before it says busy', () => {
    const prerequisite = buildTechnology({ code: 'masonry', category: 'construction', level: 0 });
    const technology = buildTechnology({
      code: 'siege_engineering',
      category: 'siege',
      prerequisites: [{ code: 'masonry', level: 1 }],
    });
    const { getByText, queryByText } = renderSheet({
      technology,
      technologyByCode: new Map([
        [technology.code, technology],
        [prerequisite.code, prerequisite],
      ]),
      activeResearch: {
        technology_code: 'mining',
        target_level: 1,
        started_at: new Date().toISOString(),
        finishes_at: new Date(Date.now() + 10_000).toISOString(),
      } as unknown as ActiveResearch,
    });

    expect(getByText('technology.locked')).toBeTruthy();
    expect(queryByText('technology.busy')).toBeNull();
  });

  it('says max level before anything else', () => {
    const prerequisite = buildTechnology({ code: 'masonry', category: 'construction', level: 0 });
    const technology = buildTechnology({
      code: 'siege_engineering',
      category: 'siege',
      level: 3,
      max_level: 3,
      state: 'completed',
      prerequisites: [{ code: 'masonry', level: 1 }],
    });
    const { getByText, queryByText } = renderSheet({
      technology,
      technologyByCode: new Map([
        [technology.code, technology],
        [prerequisite.code, prerequisite],
      ]),
    });

    expect(getByText('technology.max_level')).toBeTruthy();
    expect(queryByText('technology.locked')).toBeNull();
  });

  it('swaps the label while the mutation is in flight', () => {
    mockResearchState = { mutate: mockMutate, isPending: true, error: null };
    const { getByText, queryByText } = renderSheet({});

    expect(getByText('technology.researching')).toBeTruthy();
    expect(queryByText('technology.research')).toBeNull();

    fireEvent.press(getByText('technology.researching'));
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it('keeps the sheet open and renders localized copy on a server refusal', () => {
    mockResearchState = {
      mutate: mockMutate,
      isPending: false,
      error: new ApiError(400, {
        error: { code: 'TECHNOLOGY_LOCKED', message: 'x', retryable: false },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
      } as any),
    };

    const { getByText } = renderSheet({});

    expect(getByText('errors.TECHNOLOGY_LOCKED')).toBeTruthy();
    // The sheet did not dismiss — the technology it describes is still on screen.
    expect(getByText('technologies.agriculture')).toBeTruthy();
  });

  it('names the category of a cross-category prerequisite', () => {
    const prerequisite = buildTechnology({ code: 'masonry', category: 'construction', level: 1 });
    const technology = buildTechnology({
      code: 'siege_engineering',
      category: 'siege',
      prerequisites: [{ code: 'masonry', level: 1 }],
    });
    const { getByText } = renderSheet({
      technology,
      technologyByCode: new Map([
        [technology.code, technology],
        [prerequisite.code, prerequisite],
      ]),
    });

    expect(getByText('technologies.masonry · technology.category_construction')).toBeTruthy();
  });

  it('never colours an ordinary blocker as danger', () => {
    const source = readFileSync(
      join(__dirname, '../src/features/technology/components/TechnologyDetailSheet.tsx'),
      'utf8',
    );

    expect(source).not.toContain('theme.color.danger');
    expect(source).not.toContain('variant="danger"');
    // Affordability is decided on the server snapshot alone — the interpolated
    // bar must never reach this decision.
    expect(source).not.toContain('interpolateResources');
  });
});
