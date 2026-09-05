import type { ComponentProps } from 'react';
import type { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ResourceKey } from '@castleroyale/tooling/design-tokens';

type GlyphName = ComponentProps<typeof MaterialCommunityIcons>['name'];

/**
 * The single resource-to-glyph mapping. Resources were distinguished by text
 * colour alone until this phase, which is a colour-only signal the project rules
 * forbid. Any future screen needing a resource icon (market, Phase 27; training
 * upkeep, Phase 12) reuses this instead of inventing a second mapping.
 *
 * Every name below was verified present in the installed MaterialCommunityIcons
 * glyph map before being written here.
 */
export const RESOURCE_ICONS: Record<ResourceKey, GlyphName> = {
  food: 'barley',
  wood: 'tree',
  stone: 'terrain',
  iron: 'anvil',
  gold: 'gold',
};

/** Shown beside a resource whose warehouse is full. Never a colour change. */
export const STORAGE_FULL_ICON: GlyphName = 'tray-alert';

export const RESOURCE_KEYS: ResourceKey[] = ['food', 'wood', 'stone', 'iron', 'gold'];
