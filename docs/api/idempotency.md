# Idempotency

## Why

A mobile client retries. The network drops after the server committed but before
the response arrived; the player taps twice; the app resumes and replays a queued
command. Without protection each of those becomes a second building, a second
purchase, a second reward.

## The rule

Every **mutating command** accepts an `Idempotency-Key` header. It is **required**
for anything that creates, spends or grants:

- purchase
- market trade
- reward claim
- army command (train, march, recall)
- build command
- research command
- alliance donation

Reads never require one.

## Contract

- The key is a client-generated ULID or UUID, unique per logical operation —
  **not** per retry. A retry reuses the same key; that is the entire point.
- The server stores `(player_id, endpoint, key) -> response, status, created_at`.
- **First request**: execute, store the response, return it.
- **Retry with the same key and same payload**: return the stored response with
  the original status. Do not re-execute.
- **Retry while the first is still running**: return
  `IDEMPOTENCY_REQUEST_IN_FLIGHT` (HTTP 425). The client backs off and retries.
- **Same key, different payload**: return `IDEMPOTENCY_KEY_REUSED` (HTTP 409).
  This is a client bug and must be loud, not silently served the old response.
- **Missing key on a required endpoint**: `IDEMPOTENCY_KEY_REQUIRED`.
- Records expire after 24 hours.

## Interaction with locking

Idempotency is **not** a substitute for transactional locking. They solve
different problems:

- Idempotency prevents *the same request* being applied twice.
- `SELECT ... FOR UPDATE` prevents *two different requests* spending the same
  resources.

A command needs both. Idempotency is checked first; the lock is taken inside the
transaction that performs the work.

## Testing

Every phase adding a mutating command must test:

1. The same key twice produces one effect and two identical responses.
2. Concurrent duplicates produce one effect (one succeeds, one gets in-flight or
   the stored response).
3. A different payload with the same key returns `IDEMPOTENCY_KEY_REUSED`.
