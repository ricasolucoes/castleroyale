# API versioning

## URL versioning

All endpoints are under `/api/v1/`. The major version changes only for a break
that cannot be avoided.

## What is a breaking change

**Breaking** (requires a new version):

- Removing or renaming a field
- Changing a field's type or its meaning
- Adding a required request field
- Removing an enum value the client may already handle
- Changing an error code's meaning

**Not breaking** (ship into v1):

- Adding an optional request field
- Adding a response field
- Adding a new endpoint
- Adding a new `ErrorCode` — clients must treat unknown codes as generic failures,
  which is why every client error path needs a default branch

## Client version gating

Every request sends `X-Client-Version`. The server holds a minimum supported
version per platform. Below it, every endpoint returns
`UNSUPPORTED_CLIENT_VERSION` and the app shows a forced-update screen.

This exists because mobile clients cannot be forced to update. An old client
holding a wrong model of the rules is a correctness problem, not a UX one.

## Content versions are separate

`data`, `combat` and `economy` versions (ADR-015) version the *rules*, not the
*transport*. A balance change bumps a content version and never touches `/v1`.
The client reads all three from `/api/v1/health` and can prompt an update when it
does not understand the current rules.

## Deprecation

When v2 arrives, v1 keeps serving for at least one full store-review cycle plus
the observed tail of un-updated installs. Deprecated endpoints return a
`Deprecation` header and are tracked in analytics so the shutdown date is a
measurement rather than a guess.
