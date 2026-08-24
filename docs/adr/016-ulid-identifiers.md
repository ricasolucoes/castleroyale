# ADR-016: ULID identifiers for game entities

**Status:** Accepted
**Date:** 2026-08-24

## Context

Entity ids are exposed to the client in URLs and payloads: cities, armies,
marches, battles, alliances. Sequential integers leak information — total player
count, growth rate, and above all they are **enumerable**. An attacker who can
guess `/cities/1041` through `/cities/9999` can probe for authorisation gaps at
scale.

Random UUIDv4 solves enumeration but scatters index writes, because a random
primary key inserts into a random page of a B-tree.

## Decision

**ULIDs for game entities exposed to clients.** ULIDs are 128-bit, lexicographically
sortable by creation time, and encode to 26 URL-safe characters.

- Stored as `char(26)` with the ULID as the primary key.
- Time-ordered, so inserts append to the index rather than fragmenting it.
- Not enumerable, so an id cannot be walked.

**Not everything gets a ULID.** Internal tables that are never addressed by a
client — pivot tables, reference data imported from game data, ledger lines
addressed only through their parent — keep auto-increment integers, which are
smaller and faster to join.

ULIDs are **not** an authorisation mechanism. Every read is still scoped to the
acting player, and an unguessable id is defence in depth, never the guard. IDOR is
tested explicitly in Phase 37.

## Alternatives

**Auto-increment integers everywhere.** Rejected on enumeration and information
leakage.

**UUIDv4.** Solves enumeration, rejected on index locality: random inserts into a
large table cause measurable write amplification and cache churn.

**UUIDv7.** Essentially equivalent to ULID in properties, and a reasonable
alternative. ULID chosen for its shorter, URL-friendly canonical encoding and
first-class Laravel support (`Str::ulid()`, `HasUlids`).

**Hashids over integers.** Rejected: reversible with the salt, so it is
obfuscation rather than a property of the identifier.

## Consequences

- Ids are 26 characters rather than a few digits — larger payloads and larger
  indexes. Accepted for the enumeration property.
- ULIDs encode a creation timestamp, which is a minor information disclosure.
  Acceptable: creation time of a city or battle is not sensitive here.
- Joins on `char(26)` are slower than on `bigint`, which is exactly why internal
  high-volume tables keep integer keys.
- Sorting by id is a valid proxy for creation order, which is convenient for
  pagination.
