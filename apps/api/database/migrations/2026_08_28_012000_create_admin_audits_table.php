<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 120);
            $table->string('target_type', 180);
            $table->string('target_id', 128)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audits');
    }
};
