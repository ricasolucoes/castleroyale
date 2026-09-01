/**
 * Castle Royale — design tokens.
 *
 * The single source of visual truth. No screen, component or style may hardcode
 * a colour, spacing, radius, font size or duration.
 *
 * Semantic names only. A component asks for `surface.raised`, never `#161B22`
 * and never `grey800` — literal names lock the theme.
 *
 * @see docs/design-system/tokens.md
 */

export type ThemeName = 'light' | 'dark';

/**
 * The structural shape both themes satisfy.
 *
 * Declared explicitly rather than inferred: `as const` would give each theme
 * its own literal types, so a component typed against one could not accept the
 * other. Every token name must exist in both themes — that is the point.
 */
export type ThemeColors = {
  bg: { base: string; sunken: string };
  surface: { raised: string; overlay: string };
  border: { subtle: string; strong: string };
  text: { primary: string; secondary: string; inverse: string };
  accent: { bronze: string; gold: string; steel: string };
  danger: string;
  success: string;
  warning: string;
};

export const palette: Record<ThemeName, ThemeColors> = {
  light: {
    bg: { base: '#F4EEE2', sunken: '#E8DFCC' },
    surface: { raised: '#FFFFFF', overlay: '#FFFFFF' },
    border: { subtle: '#D8CDB6', strong: '#B9A984' },
    text: { primary: '#1A1712', secondary: '#5B5344', inverse: '#F4EEE2' },
    accent: { bronze: '#B4762E', gold: '#C08A2E', steel: '#2B4B7A' },
    danger: '#A4212B',
    success: '#3F7A4F',
    warning: '#C08A2E',
  },
  dark: {
    bg: { base: '#0E1116', sunken: '#080A0E' },
    surface: { raised: '#161B22', overlay: '#1C222B' },
    border: { subtle: '#242B35', strong: '#39424F' },
    text: { primary: '#ECE6DA', secondary: '#9AA3B0', inverse: '#0E1116' },
    accent: { bronze: '#C98C3E', gold: '#DBA748', steel: '#3D6299' },
    danger: '#C4323D',
    success: '#4F9463',
    warning: '#DBA748',
  },
};

/** Resource colours are their own group so a counter is recognisable at a glance. */
export const resourceColors = {
  food: '#7A9A4F',
  wood: '#8A6236',
  stone: '#8C8C87',
  iron: '#6E7B8B',
  gold: '#C08A2E',
} as const;

/**
 * Rarity colours. Rarity is never communicated by colour alone — always pair
 * with a shape or label (docs/design-system/tokens.md, Phase 41).
 */
export const rarityColors = {
  common: '#8C8C87',
  uncommon: '#4F9463',
  rare: '#3D6299',
  epic: '#7A4F93',
  legendary: '#C08A2E',
} as const;

/** 4pt base scale. */
export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  '2xl': 32,
  '3xl': 48,
} as const;

export const radius = {
  sm: 4,
  md: 8,
  lg: 12,
  xl: 20,
  full: 9999,
} as const;

/** `numeric` uses tabular figures so a ticking counter does not jitter. */
export const typography = {
  display: { size: 32, weight: '700', lineHeight: 40 },
  title: { size: 24, weight: '700', lineHeight: 32 },
  heading: { size: 18, weight: '600', lineHeight: 24 },
  body: { size: 16, weight: '400', lineHeight: 24 },
  label: { size: 14, weight: '500', lineHeight: 20 },
  caption: { size: 12, weight: '400', lineHeight: 16 },
  numeric: { size: 16, weight: '600', lineHeight: 20, variant: 'tabular-nums' },
} as const;

export const elevation = {
  flat: 0,
  raised: 1,
  floating: 2,
  overlay: 3,
} as const;

/** Under reduce-motion every non-essential duration collapses to `instant`. */
export const motion = {
  instant: 0,
  fast: 120,
  base: 200,
  slow: 320,
  deliberate: 500,
} as const;

/**
 * Minimum touch target, in points. Visual size may be smaller; the touchable
 * area may not. Enforced by an automated sweep in Phase 41.
 */
export const MIN_TOUCH_TARGET = 44;

export type Theme = ThemeColors;
export type ResourceKey = keyof typeof resourceColors;
export type RarityKey = keyof typeof rarityColors;

export const tokens = {
  palette,
  resourceColors,
  rarityColors,
  spacing,
  radius,
  typography,
  elevation,
  motion,
  MIN_TOUCH_TARGET,
} as const;
