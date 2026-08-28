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
        Schema::create('economy_ledger', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('resource', 16);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('overflow_amount')->default(0);
            $table->string('reason');
            $table->string('reference')->nullable();
            $table->unsignedInteger('economy_version');
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
            $table->index(['world_id', 'city_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economy_ledger');
    }
};
