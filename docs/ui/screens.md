# Screen inventory

Every screen the client ships, with the phase that builds it. Detailed layout,
states and wireframes live in `docs/ui/wireframes.md`.

| Screen | Purpose | Phase |
|--------|---------|-------|
| Splash | Boot, restore session, version check | 02 |
| Login | Email/password, Apple, Google, guest | 03 |
| Registration | Create account | 03 |
| Guest Start | One-tap entry | 03 |
| World Selection | Choose a world; population and status | 04 |
| Onboarding | Name, first city, orientation | 04 |
| **Main City** | Home. Living scene, production, construction | 07 |
| Building Details | Level, effects, cost, upgrade action | 09 |
| Construction Queue | In-flight builds, timers | 09 |
| **World Map** | Pan/zoom Skia canvas, markers, selection | 06 |
| Target Details | What is on a tile; available actions | 06 |
| Army Formation | Compose an army, live power and capacity | 14 |
| March Confirmation | Target, travel time, arrival, confirm | 15 |
| Heroes | Roster with rarity and level | 13 |
| Hero Details | Attributes, skills, talents, assignment | 13 |
| Equipment | Slots and stat contribution | 13 |
| Technology Tree | DAG with locked/available/researching/done | 10 |
| Alliance | Overview, announcements, quick actions | 22 |
| Alliance Members | Roster, ranks, permissions | 22 |
| Alliance Territory | Held territory and fortresses | 23 |
| Alliance Diplomacy | Relations, treaties | 26 |
| Chat | Global, alliance, private, rally | 25 |
| Battle | Live/replay playback | 18 |
| Battle Report | Composition, losses, loot, deciding modifiers | 18 |
| Market | Orders, NPC trade, transport | 27 |
| Quests | Tutorial, daily, weekly, achievements | 28 |
| Events | Active and upcoming, rewards | 31 |
| Ranking | Power, alliance, PvP, territory, honour | 30 |
| Nobility | Current rank, next rank, exact requirements | 29 |
| Mail | In-app messages and system notices | 33 |
| Notifications | Notification centre and preferences | 33 |
| Profile | Player summary, power breakdown | 30 |
| Settings | Audio, haptics, language, accessibility, sessions | 43 |

## Every screen needs

Not optional, and checked during Phase 44:

- **Loading** — a skeleton or cached content. Never a bare generic spinner.
- **Empty** — designed, with a next action. Never a blank panel.
- **Error** — what failed and a meaningful retry. Never a raw error code.
- **Offline** — honest state; no server-authoritative action shown as confirmed.

## Cross-cutting rules

- Tokens only — no hardcoded colour, spacing or font size
- 44x44pt minimum touch targets
- Every string from the translation catalogue
- Primary action reachable one-handed
- Bottom sheets preferred over modals
