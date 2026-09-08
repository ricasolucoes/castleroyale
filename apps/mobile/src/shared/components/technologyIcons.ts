import type { ComponentProps } from 'react';
import type { MaterialCommunityIcons } from '@expo/vector-icons';

type GlyphName = ComponentProps<typeof MaterialCommunityIcons>['name'];

/**
 * The category-to-glyph mapping, sibling to `buildingIcons.ts` and following its
 * exact structure.
 *
 * Unlike the 18-building catalogue (fully known and named at UI-spec time), the
 * technology catalogue is game-data's to author and is not yet fixed in size —
 * but its 8 categories are. These are **category** icons, not per-technology
 * icons: every technology in `economy`, for example, shares `sack` regardless of
 * how many technologies that category ends up holding. A new category must be
 * added here, not left on the fallback — falling back silently would reopen the
 * colourblind-safety gap this map exists to close, the same reasoning
 * `buildingIcons.ts` records for its own fallback.
 *
 * Chosen to be silhouette-distinct from every glyph already in `buildingIcons.ts`,
 * since a prerequisite chip and a building glyph can appear in the same detail
 * sheet. Every name below was verified present in the installed
 * MaterialCommunityIcons glyph map before being written here (10-UI-SPEC.md,
 * verification run 2026-09-07).
 */
export const TECHNOLOGY_ICONS: Record<string, GlyphName> = {
  economy: 'sack',
  military: 'sword-cross',
  defense: 'shield',
  logistics: 'truck-fast',
  construction: 'hammer-wrench',
  exploration: 'compass-outline',
  alliance: 'account-group-outline',
  siege: 'target',
};

/** A category with no mapping yet. Visually distinct from every entry above, on purpose. */
export const UNMAPPED_TECHNOLOGY_ICON: GlyphName = 'help-circle-outline';

export function technologyIcon(category: string): GlyphName {
  return TECHNOLOGY_ICONS[category] ?? UNMAPPED_TECHNOLOGY_ICON;
}
