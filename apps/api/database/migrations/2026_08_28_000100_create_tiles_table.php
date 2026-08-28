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
        Schema::create('tiles', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->ulid('region_id')->index();
            $table->integer('x');
            $table->integer('y');
            $table->string('terrain', 32);
            $table->string('generation_key', 128);
            $table->unique(['world_id', 'x', 'y']);
            $table->index(['world_id', 'x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiles');
    }
};
