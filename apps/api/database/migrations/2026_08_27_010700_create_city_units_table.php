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
        Schema::create('city_units', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('unit_code');
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['world_id', 'city_id', 'unit_code']);
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_units');
    }
};
