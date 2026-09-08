# Why there are no fixture files here

Every red-path test in `rules.test.ts` builds its bad dataset in memory rather than as
a file under `data/` or `fixtures/`. A stored bad dataset placed anywhere near `data/`
risks being picked up by `validate.ts`'s own `readdirSync` loop and failing the real
validation run — exactly the invariant this test suite exists to protect. Building each
fixture inline also keeps every test's premise ("this row has a negative cost", "this
requirement points at a level that doesn't exist") visible at the point it's asserted on,
instead of requiring a reader to cross-reference a separate file.
