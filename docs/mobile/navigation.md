# Mobile navigation

## Principle

**One-handed use is the constraint.** A player holds the phone in one hand,
usually on a commute. Primary actions live in the lower third of the screen;
nothing essential sits in the top corners.

## Primary tabs

Five, and no more. A sixth tab makes every tab harder to hit.

| Tab | Purpose |
|-----|---------|
| **City** | Home. The living city scene, production, construction |
| **Map** | The world. Pan, zoom, select targets, dispatch marches |
| **Army** | Troops, composition, marches in flight |
| **Heroes** | Roster, levelling, equipment, assignment |
| **Alliance** | Members, chat, territory, rallies, diplomacy |

## Secondary destinations

Reached through a contextual menu or from the surface that owns them — never by
adding tabs:

Research · Inventory · Quests · Events · Ranking · Market · Reports · Mail ·
Profile · Settings

## Patterns

**Bottom sheets over modals.** A sheet is reachable with a thumb, dismissible with
a gesture, and does not steal the whole screen. Modals are reserved for
destructive confirmation.

**Progressive disclosure.** Advanced UI appears as it becomes relevant. A first
launch that shows every system at once is how a new player leaves (Phase 45).

**Context over navigation.** Tapping a building opens its sheet in place; it does
not navigate away from the city.

## Routing

Expo Router with `typedRoutes` enabled.

```
app/
├── (auth)/       splash, sign-in, world-select, onboarding
├── (tabs)/       city, map, army, heroes, alliance
└── _layout.tsx   providers: Query, theme, safe area, gesture handler
```

Route files stay thin — they wire a feature component from `src/features/` to a
route. Logic does not live in `app/`.

## Deep links

Scheme `dominion://`. Push notifications deep-link to the surface that matters: an
attack warning opens the threatened city, not the home screen.

## Back behaviour

Android hardware back is handled explicitly on every screen. A sheet closes; it
does not exit the app.
