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
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'alter table "personal_access_tokens" alter column "tokenable_id" type varchar(26) using "tokenable_id"::varchar(26)',
            );

            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('tokenable_id', 26)->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'alter table "personal_access_tokens" alter column "tokenable_id" type bigint using nullif("tokenable_id", \'\')::bigint',
            );

            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('tokenable_id')->change();
        });
    }
};
