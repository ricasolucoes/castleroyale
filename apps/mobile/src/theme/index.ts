/**
 * Theme access.
 *
 * Components read tokens through `useTheme()`. Nothing imports a hex value or a
 * magic number directly — see docs/design-system/tokens.md.
 */

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
  MIN_TOUCH_TARGET,
  type ThemeName,
  type ThemeColors,
} from '@dominion/tooling/design-tokens';

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
  minTouchTarget: number;
};

export function useTheme(): AppTheme {
  const scheme = useColorScheme();
  const name: ThemeName = scheme === 'light' ? 'light' : 'dark';

  return {
    name,
    color: palette[name],
    spacing,
    radius,
    typography,
    motion,
    elevation,
    resourceColors,
    rarityColors,
    minTouchTarget: MIN_TOUCH_TARGET,
  };
}
