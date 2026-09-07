---
phase: 10-technology-research
plan: 02
type: execute
wave: 2
depends_on: ["10-01"]
files_modified:
  - packages/game-data/src/validate.ts
  - packages/game-data/src/rules.ts
  - packages/game-data/test/rules.test.ts
  - packages/game-data/test/fixtures/README.md
  - packages/game-data/package.json
  - .github/workflows/ci.yml
autonomous: true
requirements: [REQ-06]

must_haves:
  truths:
    - "A dataset containing a dependency cycle is rejected and the error names every code on the cycle"
    - "A dataset containing a negative cost, a dangling reference, a duplicate id, a missing translation key, or an unlock requirement that can never be satisfied is rejected, and each error names the offending id"
    - "The cycle detector is a graph algorithm, proven by a test whose cycle is longer than any plausible depth limit"
    - "The real authored datasets pass every rule"
    - "CI fails when a dataset is bad — the validator is wired into the pipeline, not merely runnable by hand"
  artifacts:
    - path: "packages/game-data/src/rules.ts"
      provides: "The validation rules as pure, importable, individually testable functions"
      contains: "export function"
    - path: "packages/game-data/test/rules.test.ts"
      provides: "One red-path test per rule, each asserting the offending id is named"
      contains: "toContain"
  key_links:
    - from: "packages/game-data/src/validate.ts"
      to: "packages/game-data/src/rules.ts"
      via: "the CLI imports and runs the rule functions"
      pattern: "from './rules"
---

<objective>
Complete the game data validator to the full rule set `docs/game-design/technology.md`
specifies, and put every rule under test.

**Most of this already exists and must not be rebuilt.** `packages/game-data/src/validate.ts`
today already detects duplicate codes, fractional and negative costs and durations
(including `research_time_seconds`), same-dataset dangling references, and dependency
cycles via a real DFS that returns the cycle path. Its own header comment says the
remaining rules are "completed in GSD Phase 10" — that is this plan.

What is genuinely missing:
1. **Missing translation keys** — no rule reads the locale catalogues at all.
2. **Unsatisfiable unlock requirements** — a requirement naming a level higher than
   the target's `max_level` can never be met, and nothing detects it.
3. **Cross-dataset dangling references** — the graph is built per-file, so a
   technology requiring a nonexistent *building* passes today.
4. **Any test at all** — `packages/game-data` has no test suite. A validator nobody
   has watched fail is a validator nobody can trust, and this one is load-bearing
   (ADR-013).

Purpose: ROADMAP criterion 1's second half, and CONTEXT.md's "the validator is the
deliverable".
</objective>

<context>

<interfaces>
Current `validate.ts` structure — read the file, this is orientation only:

```ts
const REQUIREMENT_TYPE_BY_DATASET: Record<string,string> =
  { buildings:'building', technologies:'technology', units:'unit' };
type Problem = { dataset: string; message: string };
function fail(dataset: string, message: string): void
function readDataset(file: string): unknown
function checkDuplicateCodes(dataset: string, rows: {code?:string}[]): void
function checkNonNegative(dataset: string, code: string, label: string, value: unknown): void
function findCycle(graph: Map<string,string[]>): string[] | null   // DFS, returns the cycle path
```

The file is a **top-level script**: it reads `data/`, loops files, accumulates
`problems`, and exits non-zero if any. There are no exports and no test harness.

Locale catalogues live at `packages/localization/locales/{en,pt-BR,es}/mvp.json`,
nested objects addressed by dotted key (`technologies.agriculture`).
</interfaces>

<canonical_refs>
- `docs/game-design/technology.md` § The validator — the six required rules, and the
  "error messages name the offending id" mandate.
- `docs/adr/013-*` — why the validator is load-bearing.
</canonical_refs>

<trap>
**Do not rewrite `findCycle`.** It is already a correct DFS over an explicit graph and
already returns the offending path for the error message. Rewriting it risks losing the
path-reporting behaviour that makes the error message useful. Your job is to test it and
to widen what feeds it, not to replace it.

**Do not make the real datasets the test fixtures.** A test that asserts "a cycle is
rejected" cannot use `technologies.json`, because that file must always be valid. Every
red-path test builds its own in-memory bad dataset.
</trap>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Extract the rules into a testable module, without changing behaviour</name>

  <read_first>
    - packages/game-data/src/validate.ts (the entire file — every function and the top-level loop)
    - packages/game-data/package.json (scripts, module type, whether a test runner already exists)
    - apps/mobile/package.json (how jest is configured in this monorepo, for the runner choice)
  </read_first>

  <files>
    packages/game-data/src/rules.ts,
    packages/game-data/src/validate.ts,
    packages/game-data/package.json
  </files>

  <behavior>
    - Every validation rule is an exported pure function taking data and returning `Problem[]`
    - `validate.ts` becomes a thin CLI: read files → call rules → print → exit
    - Running `npm run gamedata:validate` produces byte-identical output to before this task on the current datasets
    - No rule reads the filesystem; the CLI does the reading and passes data in
  </behavior>

  <action>
Create `packages/game-data/src/rules.ts` exporting:

```ts
export type Problem = { dataset: string; message: string };

export type Dataset = { name: string; rows: Record<string, unknown>[] };

/** Existing behaviour, moved verbatim. */
export function checkDuplicateCodes(dataset: string, rows: { code?: string }[]): Problem[];
export function checkNumericFields(dataset: string, rows: Record<string, unknown>[]): Problem[];
export function buildDependencyGraph(dataset: string, rows: Record<string, unknown>[]): Map<string, string[]>;
export function findCycle(graph: Map<string, string[]>): string[] | null;
export function checkSameDatasetReferences(dataset: string, rows: Record<string, unknown>[]): Problem[];
```

Move the existing logic across **unchanged**. `findCycle` in particular is copied
verbatim, including its `state`/`stack` mechanics and its cycle-path return. Keep
`REQUIREMENT_TYPE_BY_DATASET` in `rules.ts` and export it — Task 3 needs it.

Rewrite `validate.ts` to import from `./rules` and keep its current CLI behaviour:
same output format, same exit codes, same "No data/ directory yet" and "No datasets
authored yet" early exits.

Add a test runner to `packages/game-data/package.json`. Use **node's built-in test
runner** (`node --test`) rather than adding jest to this package — it has no test
infrastructure today, the package is pure TypeScript with no React, and adding a
second test framework to the monorepo for four files is not worth it. Add:

```json
"scripts": { ..., "test": "node --test --experimental-strip-types test/*.test.ts" }
```

Read the package's existing `type`/module setup first and adjust the invocation to
match it — if the package is ESM with a build step, run the tests against the built
output instead and say so in the SUMMARY. The requirement is that `npm test` from the
repo root runs these tests; the mechanism is at your discretion.

**Behaviour-preservation check.** Before editing, capture the current output:
`npm run gamedata:validate > /tmp/validate-before.txt 2>&1`. After refactoring, run it
again and diff. The diff must be empty.
  </action>

  <acceptance_criteria>
    - `grep -c "^export function" packages/game-data/src/rules.ts` is ≥ 5
    - `grep -c "from './rules" packages/game-data/src/validate.ts` is ≥ 1
    - `grep -c "function findCycle" packages/game-data/src/validate.ts` is 0 — it moved, it was not duplicated
    - `npm run gamedata:validate` exits 0 and its output is byte-identical to the pre-refactor capture
    - `npm run typecheck` and `npm run lint` are clean
    - `npm test` from the repo root runs the game-data package's tests (0 tests at this point is acceptable; the runner must be wired)
  </acceptance_criteria>

  <verify>
    <automated>npm run gamedata:validate &amp;&amp; npm run typecheck &amp;&amp; npm run lint</automated>
  </verify>

  <done>
    Every existing rule is an importable pure function with a test runner wired, and
    the CLI behaves exactly as it did before.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: The three missing rules</name>

  <read_first>
    - packages/game-data/src/rules.ts (as left by Task 1)
    - packages/localization/locales/en/mvp.json (the nested shape a dotted key addresses)
    - packages/game-data/data/technologies.json (the real dataset these rules must pass)
    - packages/game-data/data/buildings.json (the cross-dataset reference target)
    - docs/game-design/technology.md § The validator (the mandate)
  </read_first>

  <files>packages/game-data/src/rules.ts, packages/game-data/src/validate.ts</files>

  <behavior>
    - A `name_key` or `description_key` absent from any of the three locale catalogues is reported, naming the code, the key and the locale
    - A requirement whose `level` exceeds the referenced entry's `max_level` is reported as unsatisfiable, naming both codes and the impossible level
    - A requirement of type `building` naming a code absent from `buildings.json` is reported, and likewise `technology` requirements from within `buildings.json`
    - A requirement that a technology depends on itself at a higher level is reported as unsatisfiable
    - The real authored datasets produce zero problems
  </behavior>

  <action>
Add to `rules.ts`:

**2a. `checkTranslationKeys(dataset, rows, catalogues): Problem[]`**

```ts
export function checkTranslationKeys(
  dataset: string,
  rows: Record<string, unknown>[],
  catalogues: Record<string, unknown>,   // locale code -> parsed mvp.json
): Problem[]
```

For every row, for every property whose name ends in `_key` (this catches `name_key`
and `description_key` without hardcoding either, so a future `tooltip_key` is covered
automatically), resolve the dotted path against each catalogue. Report:

`"<code>" has <property> "<key>" which is missing from the <locale> catalogue`

Resolution walks the dotted path; a non-object encountered mid-path is a miss, not a
crash.

**2b. `checkRequirementSatisfiable(rows, maxLevelsByTypeAndCode): Problem[]`**

Build `Map<string /* "type:code" */, number /* max_level */>` from all datasets, then
for every requirement report when `requirement.level > maxLevel`:

`"<code>" level <n> requires <type> "<reqCode>" level <reqLevel>, but its max level is <maxLevel>`

Also report a self-reference at an unreachable level:

`"<code>" level <n> requires itself at level <reqLevel>`

(A technology requiring *itself* at a **lower** level is redundant but harmless and is
not an error — level N implies level N-1 was researched. Only a self-requirement at a
level ≥ the requiring level is unsatisfiable.)

**2c. `checkCrossDatasetReferences(datasets): Problem[]`**

Where the existing same-dataset check only validates `type === selfType` edges, this
validates the rest. Using `REQUIREMENT_TYPE_BY_DATASET` inverted, for every requirement
whose `type` maps to a *known* dataset, report a code absent from that dataset:

`"<code>" requires <type> "<reqCode>", which is not in <targetDataset>.json`

Requirement types with no dataset (`nobility`, `player_level`) are skipped — they are
validated by whichever phase introduces them, and skipping them must be an explicit
`continue` with a comment, not an accidental fall-through.

**2d.** Wire all three into `validate.ts`'s loop. Translation catalogues are read once
by the CLI from `packages/localization/locales/*/mvp.json` and passed in. If the
localization package is absent, report that as a single problem rather than crashing.
  </action>

  <acceptance_criteria>
    - `grep -c "export function checkTranslationKeys" packages/game-data/src/rules.ts` is 1
    - `grep -c "export function checkRequirementSatisfiable" packages/game-data/src/rules.ts` is 1
    - `grep -c "export function checkCrossDatasetReferences" packages/game-data/src/rules.ts` is 1
    - `grep -cE "_key" packages/game-data/src/rules.ts` is ≥ 1 and the implementation derives key properties by suffix, not by a hardcoded list of `name_key`/`description_key`
    - `npm run gamedata:validate` exits 0 against the real datasets
    - `npm run typecheck && npm run lint` clean
  </acceptance_criteria>

  <verify>
    <automated>npm run gamedata:validate</automated>
  </verify>

  <done>
    All six rules `docs/game-design/technology.md` mandates are implemented, and the
    real datasets pass all of them.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: One red-path test per rule, each proving the offending id is named</name>

  <read_first>
    - packages/game-data/src/rules.ts (the functions to test)
    - packages/game-data/package.json (the runner Task 1 wired)
  </read_first>

  <files>packages/game-data/test/rules.test.ts, packages/game-data/test/fixtures/README.md</files>

  <behavior>
    - Each of the six rules has at least one test that feeds it a bad dataset and asserts both that it fails AND that the message contains the offending id
    - The cycle test uses a cycle of length 5, which no depth limit below 5 would catch and which proves the algorithm walks the graph
    - A test asserts the real authored datasets produce zero problems
    - Every fixture is built in-memory in the test; no fixture file is added to `data/`
  </behavior>

  <action>
Create `packages/game-data/test/rules.test.ts`.

**The point of every test here is the message, not just the boolean.** `docs/game-design/technology.md`
says "error messages name the offending id — 'invalid dataset' is not an acceptable
failure message". So every assertion is of the form: it failed, **and** the message
contains the specific code that caused it.

Tests:

1. *"rejects a duplicate code and names it"* — two rows coded `agriculture`; assert a
   problem whose message contains `agriculture`.
2. *"rejects a negative cost and names the technology and the resource"* — a level with
   `cost: { wood: -5 }`; message contains both `masonry` and `cost.wood`.
3. *"rejects a fractional cost"* — `cost: { wood: 1.5 }`; message contains `masonry`.
4. *"rejects a dangling same-dataset reference and names both codes"* — `logistics_core`
   requires technology `does_not_exist`; message contains both.
5. *"detects a five-node cycle"* — build the graph `a→b→c→d→e→a` and assert `findCycle`
   returns non-null and that the returned path contains all five codes. Then assert a
   four-node acyclic graph with a diamond (`a→b`, `a→c`, `b→d`, `c→d`) returns `null` —
   a shared descendant is not a cycle, and an implementation that confused "visited"
   with "on the current stack" would wrongly flag it. **This diamond case is the one
   that distinguishes a real DFS from a naive one; do not omit it.**
6. *"rejects a missing translation key and names the key and the locale"* — a technology
   whose `name_key` is absent from the `es` catalogue only; message contains the key and
   `es`.
7. *"rejects a requirement above the target's max level"* — requires `masonry` level 9
   when masonry's `max_level` is 3; message contains `masonry` and `9`.
8. *"rejects a self-requirement at an unreachable level"* — `agriculture` level 2
   requires `agriculture` level 2; message contains `agriculture`.
9. *"allows a self-requirement at a lower level"* — `agriculture` level 3 requires
   `agriculture` level 2; assert zero problems. This pins the deliberate carve-out so a
   later tightening does not silently break the real dataset.
10. *"rejects a cross-dataset dangling reference"* — a technology requiring building
    `does_not_exist`; message contains both the technology code and `buildings`.
11. *"accepts the real authored datasets"* — load the actual `buildings.json`,
    `technologies.json` and the three locale catalogues from disk and assert every rule
    returns zero problems. This is the regression net: it fails the moment someone
    authors a bad row.

Add `packages/game-data/test/fixtures/README.md` explaining, in three sentences, why
fixtures are built in-memory rather than stored as files: a stored bad dataset in or
near `data/` risks being picked up by the CLI's `readdirSync` loop and failing the real
validation run.
  </action>

  <acceptance_criteria>
    - `grep -c "test(\|it(" packages/game-data/test/rules.test.ts` is ≥ 11
    - `grep -c "toContain\|assert.match\|includes(" packages/game-data/test/rules.test.ts` is ≥ 10 — every red path asserts on the message, not merely on a count
    - The five-node cycle test exists: `grep -c "five-node\|a→b→c→d→e\|'e'" packages/game-data/test/rules.test.ts` is ≥ 1
    - The diamond non-cycle case exists: `grep -ci "diamond" packages/game-data/test/rules.test.ts` is ≥ 1
    - `npm test` runs them and all pass
    - `ls packages/game-data/data/` contains no fixture or `*-bad-*` file
    - `npm run typecheck && npm run lint` clean
  </acceptance_criteria>

  <verify>
    <automated>npm test</automated>
  </verify>

  <done>
    Every validator rule has been watched failing on a dataset that violates it, with
    the offending id named in the message, and the real datasets are pinned as passing.
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 4: Wire the validator into CI so a bad dataset fails the build</name>

  <read_first>
    - .github/workflows/ci.yml (the whole file — existing jobs, their names, the `CI` check that gates merges)
    - packages/game-data/package.json (the scripts CI will call)
  </read_first>

  <files>.github/workflows/ci.yml</files>

  <behavior>
    - CI runs `npm run gamedata:validate` and the game-data test suite
    - A dataset with a cycle fails the pipeline
    - The step sits in an existing job rather than adding a new one, unless the file's structure makes a new job clearly correct
  </behavior>

  <action>
Read `.github/workflows/ci.yml` and add the validator to the pipeline. Phase 01
established a single `CI` check gating merges — do not create a second required check
that would need repository settings changed (auto-mode cannot change GitHub settings,
and a new required check would silently not be required).

Add to the existing Node/lint job, after dependency install:

```yaml
      - name: Validate game data
        run: npm run gamedata:validate

      - name: Test game data rules
        run: npm test --workspace=@castleroyale/game-data
```

Match the file's existing step naming style and indentation exactly — read a
neighbouring step and copy its shape.

**Do not** run a deliberately-bad dataset through CI to prove it fails; that would
require committing a bad dataset. The proof that the rules bite lives in Task 3's unit
tests, which CI now runs.
  </action>

  <acceptance_criteria>
    - `grep -c "gamedata:validate" .github/workflows/ci.yml` is ≥ 1
    - `grep -c "game-data" .github/workflows/ci.yml` is ≥ 1
    - `python3 -c "import yaml,sys;yaml.safe_load(open('.github/workflows/ci.yml'))"` exits 0 — the workflow is still valid YAML
    - The number of top-level jobs is unchanged from before this task (verify by counting keys under `jobs:` before and after)
  </acceptance_criteria>

  <verify>
    <automated>python3 -c "import yaml;d=yaml.safe_load(open('.github/workflows/ci.yml'));print(list(d['jobs'].keys()))"</automated>
  </verify>

  <done>
    A bad dataset cannot reach master: the validator and its tests run in the existing
    CI check.
  </done>
</task>

</tasks>

<verification>
- `npm run gamedata:validate` — exits 0
- `npm test` — the game-data suite passes, ≥ 11 tests
- `npm run typecheck && npm run lint` — clean
- `.github/workflows/ci.yml` — valid YAML, job count unchanged, validator wired
- `git status --porcelain packages/game-data/data` — empty; no fixture leaked into the real dataset directory
</verification>
