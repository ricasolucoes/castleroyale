<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * The migration conventions of docs/database/conventions.md, as code.
 *
 * Prose conventions drift; a helper that every migration calls does not. These
 * three cover the shapes that repeat across every gameplay table: a ULID-keyed
 * entity, world scoping, and a timed operation.
 *
 * Deliberately NOT here: resource columns. Those need a `CHECK (col >= 0)`
 * constraint, which SQLite cannot add via ALTER TABLE, and the default suite runs
 * on SQLite. They land with the economy module.
 */
final class GameTable
{
    /**
     * Client-addressable game entity: ULID primary key plus UTC timestamps.
     *
     * ULID rather than auto-increment so ids are non-enumerable and time-sortable,
     * and safe to expose in an API response (ADR-016).
     */
    public static function entity(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->timestamps();
    }

    /**
     * World scoping. Every gameplay query must filter on this column (ADR-012);
     * omitting it is a cross-world data leak.
     *
     * No foreign key constraint yet — the `worlds` table arrives in Phase 05, and
     * a constraint added here would make this helper unusable until then.
     */
    public static function worldScoped(Blueprint $table): void
    {
        $table->ulid('world_id')->index();
    }

    /**
     * The timed-operation triple.
     *
     * `completed_at` staying null is what makes a completion job idempotent and
     * what lets the reconciler find rows whose job never ran. Completion is never
     * inferred from `finishes_at` alone.
     */
    public static function timed(Blueprint $table): void
    {
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finishes_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->index(['finishes_at', 'completed_at']);
    }
}
