# Conventions

## PHP

- `declare(strict_types=1);` in every file. Enforced.
- Classes are `final` unless designed for extension.
- Constructor property promotion, `readonly` where the value never changes.
- Named arguments for anything with more than two parameters.
- Return types always. `void` explicitly.
- Enums are backed by short, stable strings — never integers, never renamed.
- Static factories over public constructors for value objects (`ResourceAmount::of()`).
- Exceptions are named for the rule broken, with static named constructors:
  `InvalidResourceAmount::wouldGoNegative()`.

Run `./vendor/bin/pint` before committing. It is not optional; CI checks it.

## Comments

Explain **why**, never what. A comment restating the code is noise.

Good: `// Check affordability inside the lock — outside it is the double-spend window.`
Bad: `// Loop over the resources`

Docblocks on public API where types alone do not convey intent, and on anything
whose reason for existing is non-obvious.

## Naming

- Commands (use cases): imperative — `StartBuildingUpgrade`, `DispatchMarch`.
- Domain events: past tense — `BuildingCompleted`, `MarchArrived`.
- Jobs: what they do — `CompleteBuildingUpgrade`.
- Booleans: `is`, `has`, `can` — never a negative (`not_active` is banned).
- Tests: a sentence. `it('refuses a subtraction that would go negative')`.

## TypeScript

- Strict, with `noUncheckedIndexedAccess` and `exactOptionalPropertyTypes`.
- `type` over `interface` unless declaration merging is needed.
- No `any`. `unknown` plus narrowing.
- API types come from `@castleroyale/contracts` — never hand-written.
- **Server state is TanStack Query. Client state is Zustand.** Never mix. If the
  server owns it, it does not go in a Zustand store.
- No hardcoded colour, spacing or font size — design tokens only.
- No user-facing string literal — translation catalogue only (from Phase 42;
  write new strings through the catalogue from the start).

## Database

Full rules: `docs/database/conventions.md`. The ones most often got wrong:

- Every gameplay table has `world_id`, and every query filters on it.
- Resources are `bigInteger` with a `CHECK (>= 0)`. Never `decimal`, never `float`.
- Timed rows carry `started_at`, `finishes_at`, `completed_at`.
- Index every foreign key — PostgreSQL does not.
- No native PostgreSQL enum types for data-driven values.

## Commits

Conventional commits, scoped by module:

```
feat(world): add region viewport query
fix(economy): re-check affordability inside the lock
test(combat): cover deterministic replay across versions
docs(adr): record content versioning decision
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `perf`, `build`, `ci`, `chore`.

One logical change per commit. Never a 14,000-file commit called "updates".

**Never add `Co-Authored-By` trailers.**

## Versioning

The project starts at **v0.1.0**. `v1.0.0` is reserved for a mature product in
production with real players — never the first release. Features bump minor
(`v0.2.0`), fixes bump patch (`v0.1.1`).

`CHANGELOG.md` follows the **Release Notes** format: `# Release Notes`, sections
`### ✨ Novidades`, `### 🎨 Melhorias`, `### 🐛 Correções`, `### 🔧 Técnico`, items
always as `- [x]` or `- [ ]` checkboxes. Never Keep-a-Changelog.
