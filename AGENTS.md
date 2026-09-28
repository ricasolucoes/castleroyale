# Instructions for AI agents

Read this before touching anything.

## What this repository is

**Castle Royale** — a mobile MMO of empire building, territorial conquest and
real-time strategic warfare. A Laravel API is the authority, a React Native app is
the client, and a Filament panel is the back office.

The product name is a **configuration value** (`config('game.name')`). It appears
in no namespace and no class name. Never hardcode it.

## The two rules that override everything

### 1. Before modifying any module, read its documentation and its GSD phase

Not optional. The plan was written once, deliberately. Its value only survives if
you follow it instead of re-deriving it.

For a phase, read in this order:

1. `.planning/STATE.md` — where the project actually is
2. `.planning/ROADMAP.md` §Phase NN — goal, dependencies, success criteria
3. `.planning/phases/NN-*/NN-CONTEXT.md` — **the locked decisions**
4. Everything under that CONTEXT's *Canonical References*

### 2. Never declare a task complete without running its validations

"Should work" is not a status. Run the command. Paste what it printed.

If a test fails, say so and show the output. If a step was skipped, say which and
why. A phase reported DONE that is not done costs far more than an honest blocker.

## Where things live

| Path | What |
|------|------|
| `.planning/` | **The machine-readable plan.** `/gsd:autonomous` reads this |
| `.planning/codebase/` | Stack, architecture, structure, conventions, testing, concerns |
| `.planning/phases/NN-*/` | Per-phase CONTEXT, PLAN, SUMMARY |
| `docs/adr/` | 17 architecture decision records |
| `docs/gsd/` | Human-facing plan narrative |
| `apps/api/modules/` | **Game code**, namespace `Game\` |
| `apps/api/app/` | Framework glue only — keep it thin |
| `packages/game-data/` | **All balance numbers.** Never in PHP |
| `packages/contracts/` | OpenAPI spec + generated TS types |

`.planning/` and `docs/gsd/` must stay consistent. `.planning/` wins.

## Commands

```bash
# Backend — from apps/api
./vendor/bin/pest                       # tests
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/pint                       # format
./vendor/bin/pint --test                # check only
php artisan migrate
php artisan db:seed

# Mobile / workspace — from the repository root
npm run typecheck
npm run lint
npm test
npm run contracts:check                 # regenerate TS types, fail on diff

# Plan state
node ~/.claude/get-shit-done/bin/gsd-tools.cjs roadmap analyze
```

## Never do these

- **Never trust the client.** It sends intent; the server computes outcome.
  Never accept a cost, duration, result or quantity from a request.
- **Never use a float for anything a player owns.** Use `ResourceAmount` /
  `ResourceBundle`.
- **Never call `now()` in game rules.** Inject `Game\Shared\Domain\Time\Clock`.
- **Never put a balance number in PHP.** It belongs in `packages/game-data/`.
- **Never write a query without `world_id`.** That is a cross-world data leak.
- **Never spend resources outside** a transaction with `lockForUpdate` and an
  affordability re-check **inside** the lock.
- **Never invent an error shape.** Add to the `ErrorCode` enum and `openapi.yaml`.
- **Never hand-write an API type in TypeScript.** Generate from the spec.
- **Never put server state in Zustand.** That is TanStack Query's job.
- **Never hardcode a colour, spacing or font size.** Design tokens only.
- **Never add a `Co-Authored-By` trailer** to a commit.
- **Never silently design around a locked decision.** Write an ADR and record it
  in `docs/gsd/DECISIONS.md`.
- **Never leave a `TODO`** without an entry in `.planning/codebase/CONCERNS.md`.

## Environment limitations you will hit

- **The host has no `pdo_pgsql`** and the Docker daemon may be stopped.
  PostgreSQL/PostGIS migrations run in Docker or CI, never on the host.
- **The test suite runs on SQLite in-memory.** A green suite does **not** prove a
  PostGIS migration works. Tag Postgres-only tests for the CI job.
- **`php artisan install:broadcasting` needs a TTY** and fails in an agent shell.
  Reverb is already wired — do not re-run it.
- **Use quoted heredocs (`<<'EOF'`)** when writing files containing backticks.
  An unquoted heredoc executes them.

## Scope discipline

Build what the success criteria require. Nothing more.

Every phase has an explicit non-goals list. Something valuable that does not serve
a criterion for *this* phase belongs to a later one — capture it in
`docs/gsd/DECISIONS.md` and move on.

## Commits

Conventional, scoped, one logical change each:

```
feat(world): add region viewport query
fix(economy): re-check affordability inside the lock
test(combat): cover deterministic replay across versions
```

## Versioning

Starts at **v0.1.0**. `v1.0.0` is reserved for a mature product in production —
never the first release. `CHANGELOG.md` uses the **Release Notes** format with
emoji sections and `- [x]` checkboxes. Never Keep-a-Changelog.

## Restricted use of GPT APIs (OpenAI)

- GPT (OpenAI) APIs must be used **exclusively** for:
  1. **Image generation** (direct calls/scripts like DALL-E / Image Generation, without subagents or chat loops).
  2. **Language translation and localization**.
- It is **strictly prohibited** to use GPT APIs for reasoning, code analysis, task planning, testing, or general orchestration. All reasoning and analysis are the responsibility of the primary workspace model.
