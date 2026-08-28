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
        Schema::create('worlds', function (Blueprint $table): void {
            GameTable::entity($table);
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('population')->default(0);
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('spawn_index')->default(0);
            $table->boolean('is_open')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worlds');
    }
};
