<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->boolean('is_guest')->default(false);
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->unique(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['is_guest', 'provider', 'provider_id']);
        });
    }
};
