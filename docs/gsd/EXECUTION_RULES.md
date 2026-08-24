# GSD execution rules

How to execute a phase in this repository. These rules exist because the plan was
written once, deliberately, and the value of that only survives if executing
agents follow it instead of re-deriving it.

## Where the plan lives

| Path | What it is | Who reads it |
|------|------------|--------------|
| `.planning/` | The **machine-readable** plan | GSD tooling and agents |
| `docs/gsd/` | The **human-facing** narrative | People |

`.planning/ROADMAP.md` is authoritative. If the two ever disagree, `.planning/` wins.

## The protocol

When you receive `Execute GSD Phase NN`:

1. Read `.planning/STATE.md` — where the project actually is.
2. Read `.planning/ROADMAP.md` §Phase NN — goal, dependencies, success criteria.
3. Read `.planning/phases/NN-*/NN-CONTEXT.md` — **the locked decisions**.
4. Read every document in that CONTEXT's *Canonical References*.
5. **Validate dependencies.** If a phase this one depends on is not complete, stop
   and say so. Do not build on an absent foundation.
6. Execute: migrations → domain → application → interface → tests → docs.
7. Run every validation command. Actually run them.
8. Update `.planning/STATE.md` and the `ROADMAP.md` progress table.
9. Write `NN-SUMMARY.md`.
10. Mark the phase complete **only if** the Definition of Done below is genuinely met.

## Definition of Ready

A phase may start only when:

- its dependencies are complete
- its success criteria exist and are observable
- its CONTEXT.md exists with decisions locked
- its test strategy is clear from `.planning/codebase/TESTING.md`

All 55 phases already satisfy this. If one does not, that is a bug in the plan —
report it rather than improvising.

## Definition of Done

A phase is done when **all** of these are true:

- [ ] Every success criterion in the roadmap is demonstrably true
- [ ] `./vendor/bin/pest` passes
- [ ] `./vendor/bin/phpstan analyse` reports 0 errors
- [ ] `./vendor/bin/pint --test` passes
- [ ] `npm run typecheck` and `npm run lint` pass (if the phase touched mobile)
- [ ] Migrations run cleanly, forward and back
- [ ] Documentation updated
- [ ] Security considerations addressed with a **named test**, not an assertion
- [ ] Observability and analytics implemented where the phase requires them
- [ ] No critical TODO and no broken placeholder left behind
- [ ] `NN-SUMMARY.md` written
- [ ] `.planning/STATE.md` updated

"Should work" is not a status. Run the command and paste what it said.

## Do not re-plan

The decisions in CONTEXT.md are **locked**. Do not reopen them because a different
approach seems nicer.

If a decision is *genuinely unworkable* — not merely inconvenient:

1. Stop.
2. Write an ADR in `docs/adr/` recording the new decision and what it supersedes.
3. Record it in `docs/gsd/DECISIONS.md`.
4. Continue.

Silently designing around a recorded decision is the one failure mode this whole
plan exists to prevent.

## Scope discipline

Build what the success criteria require. Nothing more.

Something valuable that does not serve a criterion for **this** phase belongs to a
later one. Capture it in `docs/gsd/DECISIONS.md` under Deferred and move on. Every
phase has an explicit non-goals list; respect it.

## Commits

Conventional commits, scoped, semantic, one logical change each:

```
feat(world): add region viewport query
test(world): cover viewport bounds rejection
docs(world): document the coordinate system
```

Never a single giant commit. **Never add `Co-Authored-By` trailers.**

## Technical debt

Accepted debt is recorded, never silent. Each entry needs an **ID**, a **reason**,
an **impact** and a **target phase** — see `.planning/codebase/CONCERNS.md`.

A `TODO` with no corresponding entry is a defect.

## Honesty

If a test fails, say so and show the output. If a step was skipped, say which and
why. If something could not be verified in this environment — the PostGIS
limitation is the standing example — say that explicitly rather than implying it
passed.

A phase reported as DONE that is not done costs more than an honest blocker.
