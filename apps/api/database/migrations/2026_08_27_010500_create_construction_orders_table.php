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
        Schema::create('construction_orders', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            GameTable::timed($table);
            $table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('building_code');
            $table->unsignedInteger('from_level');
            $table->unsignedInteger('target_level');
            $table->string('idempotency_key', 64);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
            $table->unique(['world_id', 'city_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_orders');
    }
};
