<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff access flag.
 *
 * Gates the Filament back office and Horizon. Kept as an explicit column
 * rather than a role lookup so the check is a single indexed boolean on the
 * hot authorisation path; the richer staff role/permission model arrives with
 * the admin tooling in GSD Phase 34.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_staff')->default(false)->index()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_staff');
        });
    }
};
