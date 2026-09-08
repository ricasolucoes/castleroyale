<?php

declare(strict_types=1);

use Game\Shared\Infrastructure\Database\GameTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_technologies', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('technology_code');
            $table->unsignedInteger('level');
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
            $table->unique(['world_id', 'player_id', 'technology_code']);
        });

        // `city_id` lives on the order, not on player_technologies, because the
        // *cost* of research is paid from a city's resource balance even though
        // the *technology* itself belongs to the player, not the city — a player
        // could in principle own several cities, but research and its unlocked
        // effects are player-wide. The order is the seam where those two
        // ownership models meet.
        Schema::create('research_orders', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            GameTable::timed($table);
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('technology_code');
            $table->unsignedInteger('from_level');
            $table->unsignedInteger('target_level');
            $table->string('idempotency_key', 64);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
            $table->unique(['world_id', 'player_id', 'idempotency_key']);
        });

        // One open research per player, enforced by the database. The application
        // also checks this inside a lock, but a partial unique index is what makes
        // the rule true rather than merely usually-true — the same reasoning that
        // made the cities_world_id_x_y_unique index the authority in Phase 07.
        //
        // Partial indexes are PostgreSQL syntax, but SQLite (the default test
        // suite's driver, per phpunit.xml) has supported this exact
        // `CREATE UNIQUE INDEX ... WHERE ...` syntax since 3.8.0, so the
        // statement below is not guarded on the driver — it runs identically on
        // both, verified by running migrate:fresh under each.
        DB::statement(
            'CREATE UNIQUE INDEX research_orders_one_open_per_player
             ON research_orders (world_id, player_id)
             WHERE completed_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('research_orders');
        Schema::dropIfExists('player_technologies');
    }
};
