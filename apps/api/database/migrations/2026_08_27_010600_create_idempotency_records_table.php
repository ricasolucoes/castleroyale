<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_key', 128);
            $table->string('endpoint', 160);
            $table->string('idempotency_key', 64);
            $table->string('payload_hash', 64);
            $table->string('status', 16);
            $table->text('response_json')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamps();
            $table->unique(['actor_key', 'endpoint', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
