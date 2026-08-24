# ADR-017: Hand-authored OpenAPI as the shared contract

**Status:** Accepted
**Date:** 2026-08-24

## Context

Two codebases in two languages must agree on every request and response shape.
The failure mode is silent: the API adds a field, the client's types still
compile against the old shape, and the mismatch surfaces as a runtime bug in
production.

## Decision

**`packages/contracts/openapi.yaml` is the source of truth**, hand-authored and
reviewed like code.

- **TypeScript types are generated from it** via `openapi-typescript` into
  `packages/contracts/src/generated/api.ts`. The client never hand-writes an API
  type. CI runs `npm run contracts:check`, which regenerates and fails on any diff
  — so a spec change without regenerated types cannot merge.
- **The backend is tested against it**: contract tests assert that real responses
  from real endpoints validate against the schema. The spec cannot drift from the
  implementation without a red build.
- The spec defines the envelope once — `{data, meta}` for success,
  `{error: {code, message, details, retryable}}` for failure — and every endpoint
  references it, matching `Game\Shared\Interface\Http\ApiResponse`.
- The full `ErrorCode` enum is enumerated in the spec, so the client can exhaustively
  switch on it with compile-time checking.

## Alternatives

**Generate the spec from PHP annotations or reflection** (Scramble, L5-Swagger).
Tempting, and rejected: the spec then documents whatever the code happens to do,
including its accidents. A hand-authored contract is a design artefact that the
implementation must satisfy — the direction of authority matters. It also makes
the contract reviewable before either side is built, which is what lets backend
and mobile work in parallel.

**Generate the client SDK too.** Rejected for now: generated clients impose their
own fetch and error conventions, which fight TanStack Query. Types are the high
value part; the request layer is thin and better hand-written.

**No formal contract, types duplicated by hand.** Rejected — this is the failure
mode being solved.

## Consequences

- Writing the spec is real work at the start of every API-touching phase, and it
  must come *before* the implementation to pay off.
- The generated file is committed so a fresh clone typechecks without a build step;
  it is marked `linguist-generated` so diffs collapse in review.
- Contract tests make the spec load-bearing: if it is wrong, the build is red.
- A breaking change is visible as a spec diff in review, which is exactly where the
  conversation about versioning should happen (`docs/api/versioning.md`).
