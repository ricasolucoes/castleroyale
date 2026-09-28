/**
 * Theme access.
 *
 * Components read tokens through `useTheme()`. Nothing imports a hex value or a
 * magic number directly — see docs/design-system/tokens.md.
 */

import { useMemo } from 'react';
import { useColorScheme } from 'react-native';
import {
  palette,
  spacing,
  radius,
  typography,
  motion,
  elevation,
  resourceColors,
  rarityColors,
  terrainColors,
  MIN_TOUCH_TARGET,
  type ThemeName,
  type ThemeColors,
} from '@castleroyale/tooling/design-tokens';

export type AppTheme = {
  name: ThemeName;
  color: ThemeColors;
  spacing: typeof spacing;
  radius: typeof radius;
  typography: typeof typography;
  motion: typeof motion;
  elevation: typeof elevation;
  resourceColors: typeof resourceColors;
  rarityColors: typeof rarityColors;
  terrainColors: typeof terrainColors;
  minTouchTarget: number;
};

export function useTheme(): AppTheme {
  const scheme = useColorScheme();
  const name: ThemeName = scheme === 'light' ? 'light' : 'dark';

  // Memoised on the theme name, and that matters well beyond avoiding an
  // object allocation: this value is a dependency of the `useMemo` that
  // records the world map's Skia picture. Returning a fresh object per render
  // — which this hook used to do — invalidated that memo every single render,
  // so the map re-recorded every path and paint in the viewport on any state
  // change at all. Everything below the name is a module constant, so the
  // identity is safe to hold.
  return useMemo(
    () => ({
      name,
      color: palette[name],
      spacing,
      radius,
      typography,
      motion,
      elevation,
      resourceColors,
      rarityColors,
      terrainColors,
      minTouchTarget: MIN_TOUCH_TARGET,
    }),
    [name],
  );
}
