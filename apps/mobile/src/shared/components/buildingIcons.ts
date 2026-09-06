import type { ComponentProps } from 'react';
import type { MaterialCommunityIcons } from '@expo/vector-icons';

type GlyphName = ComponentProps<typeof MaterialCommunityIcons>['name'];

/**
 * The building-code-to-glyph mapping, replacing `CitySlot.tsx`'s binary
 * `category === 'core' ? 'castle' : 'sprout-outline'` ternary — safe only while
 * the catalogue held two buildings, broken the moment the full eighteen shipped.
 *
 * Governing rule: any building whose entire function is producing exactly one
 * resource reuses that resource's glyph from `resourceIcons.ts` (`farm`/`barley`,
 * `lumber_mill`/`tree`, `quarry`/`terrain`, `iron_mine`/`anvil`, `treasury`/`gold`)
 * — this reinforces an association the player already learned from the resource
 * bar instead of asking them to memorise an eighteenth symbol. Every other
 * building gets a dedicated, silhouette-distinct glyph. A new code must be added
 * here, not left on the fallback — falling back silently would reopen the
 * colourblind-safety gap this map exists to close. Note the `gold` *glyph name*
 * below is unrelated to the `accent.gold` *colour token*: like every category
 * icon it is tinted `text.secondary`.
 *
 * Every name below was verified present in the installed MaterialCommunityIcons
 * glyph map before being written here.
 */
export const BUILDING_ICONS: Record<string, GlyphName> = {
  palace: 'castle',
  barracks: 'sword',
  archery_range: 'bow-arrow',
  stable: 'horse-variant',
  siege_workshop: 'tank',
  academy: 'school',
  embassy: 'handshake',
  marketplace: 'store',
  warehouse: 'warehouse',
  hospital: 'hospital-box',
  walls: 'shield-home-outline',
  watchtower: 'binoculars',
  farm: 'barley',
  lumber_mill: 'tree',
  quarry: 'terrain',
  iron_mine: 'anvil',
  treasury: 'gold',
  tavern: 'beer',
};

/** A code with no mapping yet. Visually distinct from every entry above, on purpose. */
export const UNMAPPED_BUILDING_ICON: GlyphName = 'home-city-outline';

/** Marks a cost the player cannot currently meet. Never a colour change. */
export const COST_SHORTFALL_ICON: GlyphName = 'alert-circle-outline';

export function buildingIcon(code: string): GlyphName {
  return BUILDING_ICONS[code] ?? UNMAPPED_BUILDING_ICON;
}
