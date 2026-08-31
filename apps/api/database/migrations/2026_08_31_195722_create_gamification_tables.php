<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->integer('level')->default(1);
            $table->bigInteger('xp')->default(0);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gamification_achievements', function (Blueprint $table) {
            $table->id();
            $table->string('google_play_id')->nullable()->unique();
            $table->string('internal_id')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('category');
            $table->integer('xp_reward')->default(0);
            $table->boolean('is_incremental')->default(false);
            $table->integer('max_steps')->default(1);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        Schema::create('gamification_player_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained('gamification_achievements')->cascadeOnDelete();
            $table->integer('current_steps')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('synced_with_google_play_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'achievement_id'], 'player_achievement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_player_achievements');
        Schema::dropIfExists('gamification_achievements');
        Schema::dropIfExists('gamification_progressions');
    }
};
