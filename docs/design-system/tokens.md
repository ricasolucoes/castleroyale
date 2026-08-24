# Design tokens

The single source of visual truth. Tokens live in
`packages/tooling/design-tokens/index.ts` and are imported by the mobile app.

**No screen, component or style may hardcode a colour, spacing, radius, font size
or duration.** If a value is not a token, it is a bug.

## Direction

Historical empire, military strategy, a living map, a premium modern interface.
Rich without becoming a medieval carnival of glowing buttons. Original identity —
this deliberately does not imitate any existing game.

Materials the palette is drawn from: parchment, stone, bronze, gold, steel, wood,
deep blue, military red, with dramatic lighting and metallic detail.

## Colour

Semantic names, never literal ones. A component asks for `surface.raised`, never
`#1A1F27` and never `grey800` — literal names lock the theme.

| Token | Light | Dark | Use |
|-------|-------|------|-----|
| `bg.base` | `#F4EEE2` | `#0E1116` | Screen background |
| `bg.sunken` | `#E8DFCC` | `#080A0E` | Wells, insets |
| `surface.raised` | `#FFFFFF` | `#161B22` | Cards, panels |
| `surface.overlay` | `#FFFFFF` | `#1C222B` | Sheets, dialogs |
| `border.subtle` | `#D8CDB6` | `#242B35` | Dividers |
| `border.strong` | `#B9A984` | `#39424F` | Panel edges |
| `text.primary` | `#1A1712` | `#ECE6DA` | Body |
| `text.secondary` | `#5B5344` | `#9AA3B0` | Supporting |
| `text.inverse` | `#F4EEE2` | `#0E1116` | On accent fills |
| `accent.bronze` | `#B4762E` | `#C98C3E` | Primary action |
| `accent.gold` | `#C08A2E` | `#DBA748` | Rarity, highlight |
| `accent.steel` | `#2B4B7A` | `#3D6299` | Info, links |
| `danger` | `#A4212B` | `#C4323D` | Destructive, defeat |
| `success` | `#3F7A4F` | `#4F9463` | Confirmation, victory |
| `warning` | `#C08A2E` | `#DBA748` | Caution |

Resource colours (`food`, `wood`, `stone`, `iron`, `gold`) are their own token
group so a counter is recognisable at a glance.

Rarity colours (`common`, `uncommon`, `rare`, `epic`, `legendary`) likewise — and
rarity is **never communicated by colour alone**, always with a shape or label too
(Phase 41).

## Contrast

Every text token against every background it may sit on meets **WCAG AA**. This is
validated at the token level by a test, so a new component inherits compliance
rather than being audited separately.

## Spacing

4pt base scale: `xs 4`, `sm 8`, `md 12`, `lg 16`, `xl 24`, `2xl 32`, `3xl 48`.

## Radius

`sm 4`, `md 8`, `lg 12`, `xl 20`, `full 9999`.

## Typography

| Token | Size | Weight | Use |
|-------|------|--------|-----|
| `display` | 32 | 700 | Screen titles |
| `title` | 24 | 700 | Section headers |
| `heading` | 18 | 600 | Card titles |
| `body` | 16 | 400 | Default |
| `label` | 14 | 500 | Buttons, labels |
| `caption` | 12 | 400 | Metadata |
| `numeric` | 16 | 600 | Tabular figures — resource counters and timers |

`numeric` uses tabular figures so a ticking counter does not jitter.

All sizes scale with the system font setting (Phase 41).

## Elevation

`flat 0`, `raised 1`, `floating 2`, `overlay 3`. Shadow and border treatment differ
per theme; components ask for the level, never the shadow.

## Motion

`instant 0`, `fast 120ms`, `base 200ms`, `slow 320ms`, `deliberate 500ms`.

Under reduce-motion, every non-essential duration collapses to `instant` (Phase 41).

## Touch targets

Minimum **44x44pt**, always. An automated sweep enforces it (Phase 41). Visual size
may be smaller; the touchable area may not.
