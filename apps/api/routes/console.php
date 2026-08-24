<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Timed gameplay (construction, research, training, marches) is driven by
| delayed queue jobs, not by polling. The schedule exists as the safety net:
| if a worker died holding a job, or Redis lost a delayed entry, the
| reconcilers below find the overdue rows and finish them.
|
| Every reconciler must be idempotent — it will race with the job it is
| backstopping. See docs/backend/schedulers.md.
|
| Reconcilers are registered by the phase that introduces their subsystem:
|   construction   GSD Phase 09
|   research       GSD Phase 10
|   training       GSD Phase 12
|   marches        GSD Phase 15
|   battles        GSD Phase 17
|
*/

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')
    ->daily();

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
