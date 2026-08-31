import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { WorldTileDetailSheet } from '../src/features/world/components/WorldTileDetailSheet';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

// Mock translation
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

// Mock bottom sheet
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

describe('WorldTileDetailSheet', () => {
  const mockTile = {
    id: '123',
    region_id: 'region-456',
    x: 10,
    y: 20,
    terrain: 'forest' as const,
  };

  it('renders server terrain and coordinate values when ready', () => {
    const { getByText } = render(
      <WorldTileDetailSheet
        tile={mockTile}
        status="ready"
        open={true}
        onClose={jest.fn()}
        onRetry={jest.fn()}
        onReset={jest.fn()}
      />
    );

    expect(getByText('world.selected_tile')).toBeTruthy();
    expect(getByText('world.coordinates {"x":10,"y":20}')).toBeTruthy();
    expect(getByText('world.terrain {"terrain":"forest"}')).toBeTruthy();
    expect(getByText('world.region {"region":"region-456"}')).toBeTruthy();
  });

  it('renders error state and localized text without guessing terrain', () => {
    const { getByText, queryByText } = render(
      <WorldTileDetailSheet
        tile={null}
        status="error"
        open={true}
        onClose={jest.fn()}
        onRetry={jest.fn()}
        onReset={jest.fn()}
      />
    );

    expect(getByText('world.tile_error')).toBeTruthy();
    expect(getByText('common.retry')).toBeTruthy();
    expect(queryByText('world.terrain')).toBeNull();
  });

  it('renders empty state and reset action', () => {
    const onReset = jest.fn();
    const { getByText, queryByText } = render(
      <WorldTileDetailSheet
        tile={null}
        status="empty"
        open={true}
        onClose={jest.fn()}
        onRetry={jest.fn()}
        onReset={onReset}
      />
    );

    expect(getByText('world.no_tiles')).toBeTruthy();
    // In Button component, title uses inner Text component
    const resetButton = getByText('world.reset_to_city');
    expect(resetButton).toBeTruthy();
    expect(queryByText('world.terrain')).toBeNull();
    
    fireEvent.press(resetButton);
    expect(onReset).toHaveBeenCalled();
  });

  it('renders stale and loading states', () => {
    const { getByText, rerender } = render(
      <WorldTileDetailSheet
        tile={null}
        status="loading"
        open={true}
        onClose={jest.fn()}
        onRetry={jest.fn()}
        onReset={jest.fn()}
      />
    );

    expect(getByText('world.tile_loading')).toBeTruthy();

    rerender(
      <WorldTileDetailSheet
        tile={null}
        status="stale"
        open={true}
        onClose={jest.fn()}
        onRetry={jest.fn()}
        onReset={jest.fn()}
      />
    );

    expect(getByText('world.tile_stale')).toBeTruthy();
  });
  
  it('prevents direct API calls from the detail sheet', () => {
    const source = readFileSync(
      join(__dirname, '../src/features/world/components/WorldTileDetailSheet.tsx'),
      'utf8',
    );

    expect(source).not.toContain('fetch');
    expect(source).not.toContain('apiRequest');
    expect(source).not.toContain('useQuery');
  });
});
