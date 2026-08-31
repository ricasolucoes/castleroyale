<?php

declare(strict_types=1);

use Game\Shared\Infrastructure\Database\GameTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alliances', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('leader_player_id')->constrained('players')->cascadeOnDelete();
            $table->string('name', 32);
            $table->string('tag', 5);
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('max_members');
            $table->unique(['world_id', 'name']);
            $table->unique(['world_id', 'tag']);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
        });

        Schema::create('alliance_members', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('role', 24);
            $table->unique(['world_id', 'player_id']);
            $table->unique(['world_id', 'alliance_id', 'player_id']);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alliance_members');
        Schema::dropIfExists('alliances');
    }
};
