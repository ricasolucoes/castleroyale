<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The default exists so the column can be added NOT NULL on both SQLite and
        // PostgreSQL without a two-step nullable dance. It is a migration mechanism,
        // not a runtime value: every write goes through EconomyLedger::record(),
        // which always supplies both parties, and a test asserts no gameplay row
        // carries 'system:legacy'.
        Schema::table('economy_ledger', function (Blueprint $table): void {
            $table->string('source', 64)->default('system:legacy')->after('city_id');
            $table->string('destination', 64)->default('system:legacy')->after('source');
            $table->index(['world_id', 'source']);
            $table->index(['world_id', 'destination']);
        });

        // Rows written before this migration still record a real direction: a credit
        // arrived at the city, a debit left it. The faucet or sink on the other end
        // was never captured, so it is named honestly as legacy rather than guessed.
        DB::table('economy_ledger')->where('amount', '>=', 0)->update([
            'source' => 'system:legacy',
            'destination' => DB::raw("'city:' || city_id"),
        ]);

        DB::table('economy_ledger')->where('amount', '<', 0)->update([
            'source' => DB::raw("'city:' || city_id"),
            'destination' => 'system:legacy',
        ]);
    }

    public function down(): void
    {
        Schema::table('economy_ledger', function (Blueprint $table): void {
            $table->dropIndex(['world_id', 'source']);
            $table->dropIndex(['world_id', 'destination']);
            $table->dropColumn(['source', 'destination']);
        });
    }
};
