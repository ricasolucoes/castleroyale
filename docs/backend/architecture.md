# Backend architecture

## Layout

```
apps/api/
├── app/                    Framework glue only — providers, framework contracts
├── modules/                Game code, namespace Game\
│   ├── Shared/             The kernel every module may depend on
│   └── <Module>/
│       ├── Domain/         Pure rules. No framework, no I/O.
│       ├── Application/    Use cases, DTOs, ports
│       ├── Infrastructure/ Eloquent models, repositories, adapters
│       └── Interface/      Controllers, requests, resources, jobs, Filament
├── config/game.php         Product identity, structural limits, versions
└── tests/
    ├── Unit/               Pure domain. No container, no database.
    ├── Feature/            Full application, fresh database.
    └── Architecture/       The boundaries, enforced.
```

Layers are used **where they earn their keep**. A module whose whole job is a
lookup table does not need four folders holding one class each — that is
ceremony, and ADR-001 explicitly rejects it.

## The dependency rule

```
Interface  ->  Application  ->  Domain
Infrastructure  ->  Domain
```

`Domain` depends on nothing but PHP and `Game\Shared\Domain`. Enforced by
`tests/Architecture/ArchitectureTest.php`:

- the domain layer may not import `Illuminate\*`
- the domain layer may not call `now()`, `config()`, `auth()`, `request()`, `cache()`
- everything under `Game\` declares `strict_types`
- `dd`, `dump`, `var_dump`, `die`, `sleep` appear nowhere

Cross-module access goes through published domain events (ADR-007) or an exported
application service. Never another module's Eloquent model or `Domain` namespace.

## The Shared kernel

| Concern | Class |
|---------|-------|
| Server-authoritative time | `Shared\Domain\Time\Clock`, `Infrastructure\Time\SystemClock`, `Domain\Time\FrozenClock` |
| Integer economy | `Shared\Domain\Economy\ResourceAmount`, `ResourceBundle`, `ResourceType` |
| Error catalogue | `Shared\Application\Error\ErrorCode`, `GameException` |
| Response envelope | `Shared\Interface\Http\ApiResponse` |
| Correlation | `Shared\Interface\Http\Middleware\AttachRequestContext` |

`Clock` is injected, never `now()`. This is what makes timed gameplay testable and
what keeps the device clock out of the rules.

## The canonical command shape

Every action that spends resources looks like this. Deviating from it is how
double-spends happen.

```php
return DB::transaction(function () use ($cityId, $command): BuildingUpgrade {
    // 1. Lock the owning row. Everything else derives from this.
    $city = City::query()->lockForUpdate()->findOrFail($cityId);

    // 2. Recompute cost server-side from game data. Never from the request.
    $cost = $this->costs->upgradeCost($command->buildingCode, $city->levelOf(...));

    // 3. Re-check affordability INSIDE the lock. Outside it is the race window.
    if (! $city->resources()->covers($cost)) {
        throw GameException::of(
            ErrorCode::InsufficientResources,
            'Not enough resources.',
            ['missing' => $city->resources()->shortfallAgainst($cost)],
        );
    }

    // 4. Mutate and write the ledger in the same transaction.
    $this->ledger->debit($city, $cost, reason: 'building.upgrade', reference: $command->key);

    // 5. Persist timing from the Clock, in UTC.
    return BuildingUpgrade::start($city, $command->buildingCode, $this->clock->now());
});
```

Idempotency is checked **before** entering this transaction; locking handles
concurrent *different* requests, idempotency handles the *same* request twice.
See `docs/api/idempotency.md`.

## Timed operations

1. Command validates, debits, writes `started_at` and `finishes_at`, dispatches a
   delayed job.
2. The job completes the operation **idempotently** — guarded on `completed_at`
   being null.
3. A scheduled **reconciler** finds overdue rows whose job never ran (worker died,
   Redis lost the delayed entry) and completes them.

The reconciler races the job by design, which is exactly why step 2 must be
idempotent. See `jobs-and-queues.md` and `schedulers.md`.
