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
        $alreadyScoped = Schema::hasColumn('idempotency_records', 'actor_key');

        if (! $alreadyScoped) {
            Schema::table('idempotency_records', function (Blueprint $table): void {
                $table->string('actor_key', 128)->nullable()->after('id');
            });

            DB::table('idempotency_records')->whereNull('actor_key')->update(['actor_key' => 'anonymous']);

            Schema::table('idempotency_records', function (Blueprint $table): void {
                $table->dropUnique('idempotency_records_endpoint_idempotency_key_unique');
                $table->unique(['actor_key', 'endpoint', 'idempotency_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('idempotency_records', function (Blueprint $table): void {
            $table->dropUnique('idempotency_records_actor_key_endpoint_idempotency_key_unique');
            $table->unique(['endpoint', 'idempotency_key']);
            $table->dropColumn('actor_key');
        });
    }
};
