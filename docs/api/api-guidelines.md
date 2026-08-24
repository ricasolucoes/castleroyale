# API guidelines

## Versioning and shape

Every client-facing endpoint lives under `/api/v1/`. See `versioning.md` for the
policy on breaking changes.

## The envelope

There are exactly two response shapes. Nothing else may reach a client.

**Success**

```json
{
  "data": { },
  "meta": { }
}
```

`meta` is omitted when empty. Collections put pagination in `meta`.

**Failure**

```json
{
  "error": {
    "code": "INSUFFICIENT_RESOURCES",
    "message": "Not enough resources.",
    "details": { "missing": ["wood", "stone"] },
    "retryable": false
  }
}
```

Both are produced by `Game\Shared\Interface\Http\ApiResponse`. All API exceptions
funnel through the single `render` closure in `bootstrap/app.php`, so an
unhandled error cannot escape as HTML.

## Error codes

**The client branches on `code`, never on `message`.** Messages are localised and
change freely; codes are frozen once shipped.

The catalogue is `Game\Shared\Application\Error\ErrorCode`. Each code maps to a
default HTTP status and a `retryable` flag that tells the client whether
retrying the identical request could succeed.

Adding a code: append to the enum, add it to `openapi.yaml`, and give it a status
in `httpStatus()`. Never reuse or repurpose an existing code.

## Idempotency

Any request that creates, spends or grants must accept an `Idempotency-Key`
header. See `idempotency.md`.

## Authentication

`Authorization: Bearer <token>`. See `authentication.md`.

## Client headers

| Header | Purpose |
|--------|---------|
| `X-Request-Id` | Client correlation id; sanitised and echoed back |
| `X-Client-Version` | Client build, for compatibility gating |
| `X-Client-Platform` | `ios` / `android` |
| `Idempotency-Key` | Required on mutating commands |

An unsupported client version receives `UNSUPPORTED_CLIENT_VERSION`.

## Pagination

Cursor-based, not offset — the world changes under the reader.

```json
{ "data": [ ], "meta": { "next_cursor": "01J...", "has_more": true } }
```

## Rules

- **Never trust a client-supplied outcome.** The client sends intent; the server
  computes result (ADR-006).
- **Never return another player's private data**, even by id. Scope every read to
  the acting player. A wrong id returns `NOT_FOUND` or `CITY_NOT_OWNED` — never a
  leak, and never a different error for "exists but not yours" versus "does not
  exist" where that distinction is itself intelligence.
- **Never accept mass assignment.** Requests are validated into explicit DTOs.
- **Always scope by `world_id`.**
- Timestamps are ISO-8601 UTC with an explicit offset (`2026-08-24T07:00:00+00:00`).
- Durations are integer seconds, never a formatted string.
- Resource quantities are integers (ADR-010).
