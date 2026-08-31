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
        Schema::create('city_buildings', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('slot', 64);
            $table->string('building_code');
            $table->unsignedInteger('level')->default(1);
            $table->unique(['world_id', 'city_id', 'slot']);
            $table->unique(['world_id', 'city_id', 'building_code']);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_buildings');
    }
};
