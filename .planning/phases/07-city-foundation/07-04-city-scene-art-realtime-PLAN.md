---
phase: 07-city-foundation
plan: 04
type: execute
wave: 4
depends_on: ["07-03"]
files_modified:
  - tools/generate-city-assets.py
  - apps/mobile/assets/city/city_ground.png
  - apps/mobile/assets/city/city_ground.prompt.md
  - apps/mobile/assets/city/slot_empty_icon.png
  - apps/mobile/assets/city/slot_empty_icon.prompt.md
  - apps/mobile/assets/city/slot_category_core.png
  - apps/mobile/assets/city/slot_category_core.prompt.md
  - apps/mobile/assets/city/slot_category_economy.png
  - apps/mobile/assets/city/slot_category_economy.prompt.md
  - apps/mobile/src/features/city/components/CityScene.tsx
  - apps/mobile/src/features/city/components/CitySlot.tsx
  - apps/mobile/src/features/city/realtime/cityChannel.ts
  - apps/mobile/src/features/city/realtime/useCityRealtime.ts
  - apps/mobile/__tests__/city-realtime.test.ts
  - apps/mobile/__tests__/city-scene.test.tsx
  - apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php
  - apps/api/modules/Construction/Application/ConstructionCompletionService.php
  - apps/api/tests/Feature/City/CityChannelAuthorizationTest.php
  - docs/realtime/events.md
  - docs/realtime/architecture.md
  - .planning/codebase/CONCERNS.md
autonomous: true
requirements: [REQ-01, REQ-08]

must_haves:
  truths:
    - "The city scene is painted with generated art, not a flat placeholder, and every asset can be regenerated from its recorded prompt"
    - "The city.{id} channel authorises the owner and denies a non-owner, an unknown city and a cross-world player"
    - "A city state change is published as a fact on the owner's private city channel"
    - "The client subscribed to its city channel refreshes server state when that fact arrives, without writing to a parallel store"
  artifacts:
    - path: "apps/mobile/assets/city/city_ground.prompt.md"
      provides: "Provenance: model id, ISO date and the full prompt"
      contains: "gemini-3.1-flash-image"
    - path: "apps/mobile/assets/city/slot_empty_icon.png"
      provides: "Alpha-composited empty-plot glyph replacing the interim vector icon"
    - path: "apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php"
      provides: "Queued private broadcast on city.{id} carrying identifiers only"
      contains: "PrivateChannel"
    - path: "apps/api/tests/Feature/City/CityChannelAuthorizationTest.php"
      provides: "Allow and deny paths for the city channel"
      min_lines: 40
    - path: "apps/mobile/src/features/city/realtime/useCityRealtime.ts"
      provides: "Subscription that invalidates the ['game','city'] query"
      contains: "invalidateQueries"
  key_links:
    - from: "apps/mobile/src/features/city/components/CityScene.tsx"
      to: "apps/mobile/assets/city/city_ground.png"
      via: "Image with resizeMode cover behind the measured frame"
      pattern: "city_ground"
    - from: "apps/api/modules/Construction/Application/ConstructionCompletionService.php"
      to: "apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php"
      via: "dispatch after an order completes"
      pattern: "CityStateChanged::dispatch"
    - from: "apps/mobile/src/features/city/realtime/useCityRealtime.ts"
      to: "POST /broadcasting/auth"
      via: "pusher:subscribe authorised with the bearer token from SecureStorage"
      pattern: "pusher:subscribe"
---

# Plan 07-04: City scene art and live city updates

<objective>
Finish the scene with generated artwork instead of a flat background, and close
the realtime half of the phase: prove the private city channel both ways, publish
a city state change on it, and have the client refresh from it.

Purpose: ROADMAP Phase 07 success criterion 4 (`city.{id}` authorises the owner
and denies non-owners, verified by a test) plus the plan-04 mandate "city realtime
channel and live state updates". The project's asset rule
(`/Users/sierra/Dev/Jogos/CLAUDE.md`) forbids shipping the scene on a placeholder.

Output: four Gemini-generated assets with provenance wired into the scene, a
queued `city.state_changed` broadcast, a dedicated channel authorisation test, and
a client subscription that invalidates the city query.
</objective>

## Context

@.planning/phases/07-city-foundation/07-CONTEXT.md
@.planning/phases/07-city-foundation/07-UI-SPEC.md
@/Users/sierra/Dev/Jogos/CLAUDE.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONCERNS.md

**Decisions locked in this plan (autonomous mode — recorded, not re-opened):**

1. **No new npm dependency for realtime.** Reverb speaks the Pusher protocol over
   a plain WebSocket, which React Native provides globally. Adding
   `laravel-echo` + `pusher-js` would mean a lockfile change, a native-free but
   awkward jest mock, and an install that can fail offline — for a handshake that
   is roughly a hundred lines. The transport is written against an injectable
   socket factory so tests drive it with a fake and never open a socket.
2. **Reconnection, backoff, jitter and sequence-gap resync are out of scope.**
   `docs/realtime/architecture.md` and ROADMAP Phase 40 own those explicitly. This
   plan ships subscribe → receive → invalidate, and nothing that would have to be
   rewritten when Phase 40 lands.
3. **`city.state_changed` is a new catalogue entry.** `docs/realtime/events.md`
   assigns no city event to Phase 07 — `resources.updated` is 08 and
   `building.*` is 09. Rather than invent an undocumented event inline, add the
   row to the catalogue. Payload is identifiers only, per the catalogue's payload
   rules: the client refetches, it never trusts a pushed entity.
4. **The event class lives in the City module** (`Game\City\Interface\Broadcasting`)
   because the city owns the channel, and is dispatched from
   `ConstructionCompletionService`, which is the single place an order actually
   completes — covering both the read path and the queued completion job. This is
   publishing a fact across modules, not reaching into another module's Eloquent
   models or `Domain` namespace, so it stays inside the ADR-007 rule.

<interfaces>
Everything needed, copied from the codebase.

`GameBootstrapService` already computes, and 07-02 already returns on `CityData`:

```php
'realtime' => [
    'key' => config('broadcasting.connections.reverb.key'),
    'host' => config('broadcasting.connections.reverb.options.host'),
    'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
    'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
    'auth_endpoint' => rtrim(config('app.url'), '/').'/broadcasting/auth',
],
```

`apps/api/bootstrap/app.php` already registers the auth route behind Sanctum:

```php
Broadcast::routes(['middleware' => ['api', 'auth:sanctum', App\Http\Middleware\CheckDeviceSession::class]]);
```

`apps/api/routes/channels.php` already implements the callback for real:

```php
Broadcast::channel('city.{cityId}', static function (Account $account, string $cityId): bool {
    return DB::table('cities')
        ->join('players', 'players.id', '=', 'cities.player_id')
        ->where('cities.id', $cityId)
        ->where('players.account_id', $account->getKey())
        ->whereColumn('cities.world_id', 'players.world_id')
        ->whereNotNull('cities.world_id')
        ->exists();
});
```

`apps/api/modules/Construction/Application/ConstructionCompletionService.php`:

```php
final class ConstructionCompletionService
{
    public function completeOverdueLocked(City $city, DateTimeImmutable $now): void
    // ... per order: updates the CityBuilding level, then $order->forceFill(['completed_at' => $now])->save();
}
```

Mobile token access — `apps/mobile/src/features/auth/SecureStorage.ts` exports
`getTokens(): Promise<{ access?: string; refresh?: string }>` (used by
`apps/mobile/src/api/client.ts` line ~150).

Existing test to model the channel test on —
`apps/api/tests/Feature/Player/PlayerChannelAuthorizationTest.php` resolves the
callback with `Broadcast::getChannels()->get('player.{playerId}')` and asserts it
`toBeTrue()` for the owner and `toBeFalse()` for a rival.
</interfaces>

## Tasks

<task type="auto">
<name>Task 1: Generate the four city assets with Gemini and wire them into the scene</name>
<files>tools/generate-city-assets.py, apps/mobile/assets/city/city_ground.png, apps/mobile/assets/city/city_ground.prompt.md, apps/mobile/assets/city/slot_empty_icon.png, apps/mobile/assets/city/slot_empty_icon.prompt.md, apps/mobile/assets/city/slot_category_core.png, apps/mobile/assets/city/slot_category_core.prompt.md, apps/mobile/assets/city/slot_category_economy.png, apps/mobile/assets/city/slot_category_economy.prompt.md, apps/mobile/src/features/city/components/CityScene.tsx, apps/mobile/src/features/city/components/CitySlot.tsx, apps/mobile/__tests__/city-scene.test.tsx</files>
<read_first>
- /Users/sierra/Dev/Jogos/CLAUDE.md
- .planning/phases/07-city-foundation/07-UI-SPEC.md
- docs/design-system/tokens.md
- apps/mobile/src/features/city/components/CityScene.tsx
- apps/mobile/src/features/city/components/CitySlot.tsx
- apps/mobile/__tests__/city-scene.test.tsx
- packages/game-data/data/buildings.json
</read_first>
<action>
**Confirm the model id first.** Fetch
<https://ai.google.dev/gemini-api/docs/models> and verify `gemini-3.1-flash-image`
still exists. Never complete or invent a model id from memory. If it has been
renamed, use the current id for the same tier and record the id you actually used
in every `.prompt.md`.

Load the key from the mirror file, never from the repository:

```bash
set -a
source "/Users/sierra/Dev/Jogos/.env"
set +a
```

Create `tools/generate-city-assets.py` using the official `google-genai` SDK
(already importable on this machine, alongside Pillow 11.3). It must:

- read `GEMINI_API_KEY` from the environment only — no key literal, ever, or the
  CI secret scan rejects the commit;
- prepend this exact style bible to every prompt (the project's locked direction,
  `docs/design-system/tokens.md` § Direction):

  > Historical empire, military strategy, a living map, a premium modern
  > interface. Rich without becoming a medieval carnival of glowing buttons.
  > Original identity — do not imitate any existing game. Materials: parchment,
  > stone, bronze, gold, steel, wood, deep blue, military red, dramatic lighting,
  > metallic detail. No text, no UI chrome, no watermark.

- generate exactly these four assets into `apps/mobile/assets/city/`:

  | File | Subject | Background |
  |---|---|---|
  | `city_ground.png` | A walled city courtyard floor seen from directly above: packed earth, stone paving, low retaining walls marking empty building plots. Roughly 1:1 composition, low contrast, quiet — it is the ground the plots sit on and must never out-compete them. | Painted scenery, **no** chroma |
  | `slot_empty_icon.png` | A single empty building foundation: a bare stone footing outline on packed earth. Simple silhouette readable at 64–128px. Not a building. | Solid chroma `#00FF00`, no ground shadow |
  | `slot_category_core.png` | A keep/palace silhouette — a squat crenellated tower. Distinct outline, no interior detail. | Solid chroma `#00FF00`, no ground shadow |
  | `slot_category_economy.png` | A granary/storehouse silhouette — a wide pitched-roof barn. Outline clearly different from the keep at small size. | Solid chroma `#00FF00`, no ground shadow |

- for every chroma asset, remove the green with Pillow and save RGBA: load the
  PNG, convert to `RGBA`, and set alpha to 0 for any pixel where
  `g > 180 and r < 120 and b < 120`, then crop to the alpha bounding box. Do not
  ask the model for transparency directly — it returns grey.
- write a sibling `<name>.prompt.md` for each asset containing the **model id
  used**, the ISO date, and the **complete** prompt (style bible plus subject plus
  background instruction). Without it the lot is not regenerable, which the
  project's asset rule forbids.

Only two categories exist in `packages/game-data/data/buildings.json` (`core`,
`economy`) — generate exactly those two glyphs. Do not pre-generate icons for
categories Phase 09 has not shipped.

**Open every PNG and check it before wiring.** Regenerate rather than ship if:
the chroma is not solid, the silhouette is unreadable at 64px, there is text or a
watermark, the ground art is loud enough to compete with the tiles, or the two
category glyphs read as the same shape. "Should be fine" is not a check.

Then wire them in:

- `CityScene.tsx`: render
  `<Image source={require('@/../assets/city/city_ground.png')} resizeMode="cover" style={StyleSheet.absoluteFill} />`
  as the **first** child of the measured frame `View`, with the slot tiles after
  it so they paint on top. Keep `backgroundColor: theme.color.bg.sunken` on the
  frame so the scene still reads if the image is slow. Use `Image` from
  `react-native`. Resolve the relative path with whatever import alias the project
  already uses for assets — check `apps/mobile/tsconfig.json` paths and
  `app.json`, and use a plain relative `require('../../../../assets/city/…')` if
  no asset alias exists.
- `CitySlot.tsx`: replace the interim `plus-circle-outline`
  `MaterialCommunityIcons` in the empty branch with
  `<Image source={require(... 'slot_empty_icon.png')} style={{ width: theme.spacing.xl, height: theme.spacing.xl, tintColor: theme.color.accent.gold }} resizeMode="contain" />`,
  and replace the `castle` / `sprout-outline` category glyphs with
  `slot_category_core.png` / `slot_category_economy.png` the same way, tinted
  `theme.color.text.secondary`. The vector icon and the generated asset must
  never both render — the asset supersedes it. Remove the now-unused
  `MaterialCommunityIcons` import from this file.

Extend `apps/mobile/__tests__/city-scene.test.tsx` with an assertion that the
scene renders exactly one image whose source resolves from the `city` asset
folder, and that `CitySlot.tsx`'s source (read with `readFileSync`) no longer
contains `plus-circle-outline`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && ls apps/mobile/assets/city/*.png | wc -l && ls apps/mobile/assets/city/*.prompt.md | wc -l && npm test --workspace=@castleroyale/mobile -- --runInBand city-scene && npm run typecheck && npm run lint</automated>
</verify>
<acceptance_criteria>
- `ls apps/mobile/assets/city/*.png | wc -l` outputs `4` and `ls apps/mobile/assets/city/*.prompt.md | wc -l` outputs `4`.
- Every `.prompt.md` contains the model id actually used and an ISO date: `grep -L 'gemini' apps/mobile/assets/city/*.prompt.md` returns nothing.
- `python3 -c "from PIL import Image; [print(f, Image.open(f).mode) for f in ['apps/mobile/assets/city/slot_empty_icon.png','apps/mobile/assets/city/slot_category_core.png','apps/mobile/assets/city/slot_category_economy.png']]"` prints `RGBA` for all three.
- `grep -rn 'AQ\.\|sk-proj-\|GEMINI_API_KEY=' tools/generate-city-assets.py` returns nothing (no key literal in the repo).
- `grep -n 'plus-circle-outline' apps/mobile/src/features/city/components/CitySlot.tsx` returns nothing.
- `grep -n 'city_ground' apps/mobile/src/features/city/components/CityScene.tsx` matches.
- `npm test --workspace=@castleroyale/mobile -- --runInBand city-scene`, `npm run typecheck` and `npm run lint` exit 0.
</acceptance_criteria>
<done>
The scene is painted with four generated, alpha-correct assets, each regenerable
from a committed prompt, and the interim vector glyphs are gone.
</done>
</task>

<task type="auto">
<name>Task 2: Publish city.state_changed and prove the channel both ways</name>
<files>apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php, apps/api/modules/Construction/Application/ConstructionCompletionService.php, apps/api/tests/Feature/City/CityChannelAuthorizationTest.php, docs/realtime/events.md, docs/realtime/architecture.md, .planning/codebase/CONCERNS.md</files>
<read_first>
- apps/api/routes/channels.php
- apps/api/tests/Feature/Player/PlayerChannelAuthorizationTest.php
- apps/api/tests/Feature/City/CityFoundationTest.php
- apps/api/modules/Construction/Application/ConstructionCompletionService.php
- docs/realtime/architecture.md
- docs/realtime/events.md
- .planning/codebase/CONCERNS.md
</read_first>
<action>
Create `apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php`:

```php
<?php

declare(strict_types=1);

namespace Game\City\Interface\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A fact: this city's persisted state moved.
 *
 * Identifiers only. The client refetches over HTTP — a full entity in the
 * payload would be a second source of truth (docs/realtime/events.md).
 */
final class CityStateChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $cityId,
        public readonly string $worldId,
        public readonly string $occurredAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('city.'.$this->cityId);
    }

    public function broadcastAs(): string
    {
        return 'city.state_changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'city_id' => $this->cityId,
            'world_id' => $this->worldId,
            'occurred_at' => $this->occurredAt,
        ];
    }

    /** Fan-out never blocks the request that caused it. */
    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
```

In `apps/api/modules/Construction/Application/ConstructionCompletionService.php`,
dispatch once per completed order, immediately after
`$order->forceFill(['completed_at' => $now])->save();`:

```php
CityStateChanged::dispatch(
    (string) $city->getKey(),
    (string) $city->world_id,
    $now->format(DATE_ATOM),
);
```

Add the import. This is the single site where an order actually completes, so both
the read path (`CityStateService`) and the queued `CompleteConstruction` job are
covered without duplicating the dispatch.

Create `apps/api/tests/Feature/City/CityChannelAuthorizationTest.php`, modelled on
`PlayerChannelAuthorizationTest.php`, resolving the callback with
`Broadcast::getChannels()->get('city.{cityId}')` and covering four cases:

1. **allows the owning account** — returns `true`.
2. **denies a rival account** — returns `false`.
3. **denies an unknown city id** — returns `false` for a well-formed ULID that has
   no row, so a probe cannot use the channel as an existence oracle.
4. **denies an account whose player belongs to a different world** — create two
   worlds, a player for the account in world B, and a city in world A owned by a
   different player; the callback must return `false`. This is the
   `whereColumn('cities.world_id', 'players.world_id')` clause, and it is the one
   nobody tests until it leaks.

Then update the documentation the phase invalidates:

- `docs/realtime/events.md`: add a catalogue row
  `| city.state_changed | city.{id} | 07 |` immediately above the
  `resources.updated` row.
- `docs/realtime/architecture.md`: the "Deny by default" section still claims
  "Every callback in `routes/channels.php` currently returns `false`". Correct it
  to name `player.{playerId}` (Phase 04) and `city.{cityId}` (Phase 07) as
  implemented and tested both ways, with `alliance`, `battle` and `world.region`
  still denying by default.
- `.planning/codebase/CONCERNS.md`: amend the DEBT-001 row so it no longer lists
  city among the unimplemented channels.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale/apps/api && ./vendor/bin/pest --filter=CityChannelAuthorization && ./vendor/bin/pest && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
</verify>
<acceptance_criteria>
- `apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php` exists and contains `new PrivateChannel('city.'` and `'city.state_changed'`.
- `grep -n 'CityStateChanged::dispatch' apps/api/modules/Construction/Application/ConstructionCompletionService.php` matches exactly once.
- `cd apps/api && ./vendor/bin/pest --filter=CityChannelAuthorization` exits 0 with 4 passing tests.
- `grep -n 'city.state_changed' docs/realtime/events.md` matches.
- `grep -n 'currently returns `false`' docs/realtime/architecture.md` returns nothing (the stale claim is corrected).
- `grep -n 'city' .planning/codebase/CONCERNS.md | grep DEBT-001` no longer lists city as unimplemented.
- `cd apps/api && ./vendor/bin/pest`, `./vendor/bin/phpstan analyse --memory-limit=1G` and `./vendor/bin/pint --test` all exit 0.
</acceptance_criteria>
<done>
The private city channel is proven to allow the owner and to deny a rival, an
unknown city and a cross-world player, and a completed construction publishes an
identifier-only fact on it.
</done>
</task>

<task type="auto">
<name>Task 3: Subscribe the city screen to its channel and invalidate on the fact</name>
<files>apps/mobile/src/features/city/realtime/cityChannel.ts, apps/mobile/src/features/city/realtime/useCityRealtime.ts, apps/mobile/app/(tabs)/city.tsx, apps/mobile/__tests__/city-realtime.test.ts</files>
<read_first>
- apps/mobile/src/api/client.ts
- apps/mobile/src/features/auth/SecureStorage.ts
- apps/mobile/app/(tabs)/city.tsx
- apps/mobile/src/features/city/components/CityScene.tsx
- apps/mobile/__tests__/api-client.test.ts
- docs/realtime/architecture.md
- docs/mobile/architecture.md
</read_first>
<action>
Create `apps/mobile/src/features/city/realtime/cityChannel.ts` — the transport,
written against an injectable socket factory so tests never open a socket:

```ts
import type { RealtimeConfig } from '@castleroyale/contracts';

export type SocketLike = {
  send: (data: string) => void;
  close: () => void;
  onopen: (() => void) | null;
  onmessage: ((event: { data: string }) => void) | null;
  onerror: ((error: unknown) => void) | null;
  onclose: (() => void) | null;
};

export type SocketFactory = (url: string) => SocketLike;

export type CityChannelOptions = {
  config: RealtimeConfig;
  cityId: string;
  onEvent: (event: string) => void;
  getAccessToken: () => Promise<string | undefined>;
  socketFactory?: SocketFactory;
  fetchImpl?: typeof fetch;
};

export function cityChannelName(cityId: string): string {
  return `private-city.${cityId}`;
}

export function cityChannelUrl(config: RealtimeConfig): string {
  const protocol = config.scheme === 'https' ? 'wss' : 'ws';
  return `${protocol}://${config.host}:${config.port}/app/${config.key}?protocol=7&client=castleroyale-mobile&version=1.0`;
}

/** Subscribe to one city's private channel. Returns an unsubscribe function. */
export function subscribeToCityChannel(options: CityChannelOptions): () => void
```

Implement the minimal Pusher handshake Reverb speaks:

1. open `cityChannelUrl(config)` via `socketFactory ?? ((url) => new WebSocket(url) as unknown as SocketLike)`;
2. on a message whose `event` is `pusher:connection_established`, read
   `socket_id` from `JSON.parse(message.data)`, then POST
   `config.auth_endpoint` with
   `{ socket_id, channel_name: cityChannelName(cityId) }`, headers
   `Content-Type: application/json`, `Accept: application/json` and
   `Authorization: Bearer ${await getAccessToken()}`, and send
   `{ event: 'pusher:subscribe', data: { auth, channel: cityChannelName(cityId) } }`
   with the `auth` value from the response body;
3. for any message whose `channel` equals `cityChannelName(cityId)` and whose
   `event` does **not** start with `pusher:` or `pusher_internal:`, call
   `onEvent(event)`;
4. the returned function sets every handler to `null` and calls `close()`.

Swallow nothing silently: on an auth failure or socket error, call `close()` and
leave the query cache untouched — a missed event costs a refresh, a wrong write
costs correctness. Reconnection, backoff and sequence-gap resync are Phase 40's
and are deliberately absent.

Create `apps/mobile/src/features/city/realtime/useCityRealtime.ts`:

```ts
export function useCityRealtime(
  cityId: string | null,
  config: RealtimeConfig | null,
  socketFactory?: SocketFactory,
): void
```

Inside a `useEffect` keyed on `[cityId, config?.key, config?.host, config?.port, config?.scheme, config?.auth_endpoint, queryClient]`,
return early when `cityId` or `config` is null (or when `config.key` is an empty
string — broadcasting is `null` by default in local config and there is nothing to
connect to). Otherwise call `subscribeToCityChannel` with
`getAccessToken: async () => (await getTokens()).access` and
`onEvent: () => { void queryClient.invalidateQueries({ queryKey: ['game', 'city'] }); }`,
and return the unsubscribe function from the effect. Never write slot or building
data into a store — the query cache is the only client-side copy of server state
(`docs/mobile/architecture.md` § The state rule).

Call it from `apps/mobile/app/(tabs)/city.tsx` on the success branch, before
rendering `CityScene`:
`useCityRealtime(cityQuery.data?.city.id ?? null, cityQuery.data?.realtime ?? null);`
— place the hook call above the early returns so hook order stays stable.

Create `apps/mobile/__tests__/city-realtime.test.ts` driving a fake socket:

- `cityChannelName('01H…')` returns `private-city.01H…`;
- `cityChannelUrl({ scheme: 'https', host: 'rt.example', port: 443, key: 'k', auth_endpoint: '…' })`
  returns `wss://rt.example:443/app/k?protocol=7&client=castleroyale-mobile&version=1.0`,
  and `scheme: 'http'` yields `ws://`;
- feeding `{"event":"pusher:connection_established","data":"{\"socket_id\":\"1.2\"}"}`
  into `onmessage` triggers exactly one `fetch` to the configured
  `auth_endpoint` with `socket_id` `1.2`, the channel name, and an
  `Authorization: Bearer …` header, followed by a `send` of a
  `pusher:subscribe` frame carrying the returned `auth` value;
- feeding `{"event":"city.state_changed","channel":"private-city.X","data":"{}"}`
  calls `onEvent` exactly once;
- feeding a `pusher_internal:subscription_succeeded` frame on the same channel
  calls `onEvent` zero times;
- feeding an event on a **different** channel calls `onEvent` zero times;
- the returned unsubscribe function calls `close()` once and clears the handlers.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && npm test --workspace=@castleroyale/mobile -- --runInBand && npm run typecheck && npm run lint</automated>
</verify>
<acceptance_criteria>
- `grep -n 'pusher:subscribe' apps/mobile/src/features/city/realtime/cityChannel.ts` matches.
- `grep -n "invalidateQueries({ queryKey: \['game', 'city'\] })" apps/mobile/src/features/city/realtime/useCityRealtime.ts` matches.
- `grep -rn 'laravel-echo\|pusher-js' apps/mobile/package.json package-lock.json` returns nothing (no dependency was added).
- `grep -rn 'zustand' apps/mobile/src/features/city/realtime/` returns nothing (realtime never writes to a client store).
- `grep -n 'useCityRealtime' 'apps/mobile/app/(tabs)/city.tsx'` matches.
- `npm test --workspace=@castleroyale/mobile -- --runInBand city-realtime` exits 0 with all seven behaviours covered.
- `npm test --workspace=@castleroyale/mobile -- --runInBand`, `npm run typecheck` and `npm run lint` exit 0.
</acceptance_criteria>
<done>
The city screen subscribes to its own private channel, refetches on a published
state change, adds no dependency, and writes nothing to a parallel store.
</done>
</task>

## Verification

```bash
npm run typecheck
npm run lint
npm test
npm run contracts:check
node --experimental-strip-types packages/localization/src/validate.ts
cd apps/api && ./vendor/bin/pest
cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G
cd apps/api && ./vendor/bin/pint --test
```

Also confirm no secret entered the tree before committing:

```bash
git diff --cached | grep -nE 'AQ\.Ab8RN6|sk-proj-|REVERB_APP_SECRET=' || echo "clean"
```

## Success Criteria

- Four generated PNGs plus four `.prompt.md` provenance files exist under
  `apps/mobile/assets/city/`; the three icon assets are RGBA with the chroma
  removed; no key literal is anywhere in the repository.
- The scene renders the generated ground and the generated glyphs; the interim
  vector icons are gone.
- `city.{cityId}` returns true for the owner and false for a rival, an unknown
  city id and a cross-world player, all four proven by test.
- A completed construction dispatches `CityStateChanged` on
  `private-city.{cityId}` with identifiers only, queued on the `realtime` tier.
- The client subscribes with no new dependency and invalidates `['game','city']`
  on a channel event, writing nothing to Zustand.
- Every repo gate is green.

<output>
After completion, create
`.planning/phases/07-city-foundation/07-04-city-scene-art-realtime-SUMMARY.md`
recording the model id actually used for generation, the no-new-dependency
realtime decision and its Phase 40 boundary, and the new `city.state_changed`
catalogue entry.
</output>
