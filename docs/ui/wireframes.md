# Wireframes

Structural intent for the essential screens. Layout, hierarchy, primary action,
and the four states every screen must have.

Not visual design — that is [`../design-system/tokens.md`](../design-system/tokens.md)
and Phase 44. The constraint driving all of these is **one-handed use**: the
primary action sits in the lower third.

---

## Main City (Phase 07)

```
┌─────────────────────────────────────┐
│ 🌾 12.4k  🪵 8.1k  🪨 5.0k  ⚙ 3.2k │ ← resource bar, always visible,
│ ⬛⬛⬛ Palace Lv7      👤 Lv12  ⚔ 4.2k│   interpolates between fetches
├─────────────────────────────────────┤
│                                     │
│         [ LIVING CITY SCENE ]       │ ← tappable buildings, ambient
│                                     │   animation, banners, smoke
│      🏛        🏹        🐎         │
│                                     │
│      🌾        🪵        🪨         │
│                                     │
├─────────────────────────────────────┤
│ ⏳ Barracks Lv5 → Lv6      02:14:31 │ ← active queues, server-driven
│ 🔬 Logistics II            00:42:10 │
├─────────────────────────────────────┤
│  🏰      🗺       ⚔       🎖      🛡 │ ← primary tabs, thumb-reachable
│ City    Map    Army   Heroes  Alli. │
└─────────────────────────────────────┘
```

**Primary action:** tap a building → its bottom sheet.
**Secondary:** collect, queue detail, contextual menu.

| State | Treatment |
|-------|-----------|
| Loading | Scene skeleton with the resource bar from cache |
| Empty | Not reachable — a city always exists |
| Error | Cached scene plus a retry banner; no fabricated numbers |
| Offline | Banner; upgrade actions disabled, not queued silently |

---

## Building Details (Phase 09)

```
┌─────────────────────────────────────┐
│                              ╳      │
│  🏹 Archery Range        Level 5    │
│  ─────────────────────────────────  │
│  Trains ranged units.               │
│                                     │
│  CURRENT              NEXT          │
│  Training +12%        +15%          │
│  Capacity 400         500           │
│                                     │
│  COST      🪵 2,400  🪨 1,800       │ ← red when short; details.missing
│            ⏱ 2h 15m                 │   from the error drives this
│                                     │
│  Requires  Palace Lv6 ✓             │
├─────────────────────────────────────┤
│   [      UPGRADE      ]             │ ← primary, thumb zone
└─────────────────────────────────────┘
```

A bottom sheet, not a modal. Disabled upgrade states name the reason —
`BUILDING_MAX_LEVEL`, `BUILD_QUEUE_FULL`, `INSUFFICIENT_RESOURCES` — never a
greyed button with no explanation.

---

## World Map (Phase 06)

```
┌─────────────────────────────────────┐
│ 🌾 12.4k  🪵 8.1k       [ 🔍 X,Y ] │
├─────────────────────────────────────┤
│                                     │
│    ▲▲▲       ⛺                     │ ← Skia canvas. No React component
│  ▲▲   ~~~~        🏰(you)           │   per tile or marker.
│      ~~~~   🏰                      │
│   ⛏      ╔══════╗                   │
│          ║ 🏰   ║ ← alliance border │
│      🌲  ╚══════╝        ⛺         │
│                                     │
├─────────────────────────────────────┤
│  🏰      🗺       ⚔       🎖      🛡 │
└─────────────────────────────────────┘
```

Pan and zoom on the UI thread. Viewport plus one screen of margin is fetched;
revisiting reads from cache. Level of detail simplifies markers as you zoom out.

**Primary action:** tap a tile → target sheet.

---

## Target Details (Phase 06/15)

```
┌─────────────────────────────────────┐
│                              ╳      │
│  🏰 Aurelia          (412, -87)     │
│  Marcus · Power 84,200              │
│  Alliance: IRON PACT                │
│  ─────────────────────────────────  │
│  🛡 Shielded — 4h 12m remaining     │ ← protection state is prominent;
│                                     │   it changes what is possible
│  Travel time      1h 48m            │
├─────────────────────────────────────┤
│  [ SCOUT ]        [ ATTACK ]        │
└─────────────────────────────────────┘
```

Refused actions explain themselves: `PLAYER_PROTECTED`,
`POWER_DIFFERENCE_TOO_LARGE`, `MARCH_LIMIT_REACHED`.

---

## Army Formation (Phase 14)

```
┌─────────────────────────────────────┐
│  Compose army            ⚔ 12,480   │ ← power recomputes live
├─────────────────────────────────────┤
│  🎖 Commander   [ General Vela  ▾ ] │
│  ─────────────────────────────────  │
│  Legionaries    ─── 2,400 ───  /5k  │
│  Archers        ─── 1,800 ───  /3k  │
│  Cavalry        ───   600 ───  /1k  │
│  ─────────────────────────────────  │
│  Capacity       48,000 / 62,000     │
│  Slowest unit   Trebuchet · 12 km/h │ ← explains the travel time
├─────────────────────────────────────┤
│   [      CONFIRM      ]             │
└─────────────────────────────────────┘
```

Sliders bound by what the garrison actually holds. Exceeding returns
`INSUFFICIENT_TROOPS` — but the UI should make it unreachable.

---

## Battle Report (Phase 18)

```
┌─────────────────────────────────────┐
│           ⚔  VICTORY                │ ← victory/defeat/draw visually
│  Aurelia · 24 Aug 09:41 UTC         │   distinct, not a colour swap
├─────────────────────────────────────┤
│  YOU                    THEM        │
│  ⚔ 12,480              ⚔ 9,200      │
│  Lost 1,840            Lost 6,700   │
├─────────────────────────────────────┤
│  WHY YOU WON                        │ ← the whole point of the screen
│  ✓ Cavalry countered archers  +35%  │
│  ✓ Hero: Vela, flanking       +12%  │
│  ✗ Uphill terrain             −8%   │
├─────────────────────────────────────┤
│  LOOT  🪵 4,200  🪨 2,100           │
├─────────────────────────────────────┤
│   [ ▶ WATCH REPLAY ]                │
└─────────────────────────────────────┘
```

The modifier breakdown is the reason determinism was worth building. A player who
lost must be able to see exactly why.

---

## Alliance (Phase 22)

```
┌─────────────────────────────────────┐
│  🛡 IRON PACT              42 / 100 │
│  Rank 3 · Power 2.4M                │
├─────────────────────────────────────┤
│  📢 "Rally at 20:00 UTC on Aurelia" │
├─────────────────────────────────────┤
│  ⚔ ACTIVE RALLY      joins in 12m   │ ← time-critical work first
│     Target: Aurelia · 6 joined      │
│     [ JOIN ]                        │
├─────────────────────────────────────┤
│  Members  Territory  Diplomacy  Chat│
└─────────────────────────────────────┘
```

Actions the player lacks permission for are **hidden**, not shown disabled —
except where hiding them would be confusing, in which case they explain
`ALLIANCE_PERMISSION_DENIED`.

---

## Universal state rules

Checked in Phase 44 for every screen:

| State | Rule |
|-------|------|
| **Loading** | Skeleton or cached content. Never a bare generic spinner |
| **Empty** | Designed, with a next action. Never a blank panel |
| **Error** | What failed, and a meaningful retry. Never a raw code |
| **Offline** | Honest banner. No server-authoritative action shown as confirmed |
