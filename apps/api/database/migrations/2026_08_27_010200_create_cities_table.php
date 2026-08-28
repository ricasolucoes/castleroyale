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
        Schema::create('cities', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('name_key');
            $table->integer('x');
            $table->integer('y');
            foreach (['food', 'wood', 'stone', 'iron', 'gold'] as $resource) {
                $table->unsignedBigInteger($resource)->default(0);
                $table->unsignedBigInteger($resource.'_capacity')->default(0);
            }
            $table->timestamp('last_accrued_at');
            $table->unique(['world_id', 'x', 'y']);
            $table->unique(['world_id', 'player_id']);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
