<?php

declare(strict_types=1);

use Game\Shared\Infrastructure\Database\GameTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            GameTable::entity($table);
            GameTable::worldScoped($table);
            $table->string('code', 64);
            $table->string('seed', 128);
            $table->unsignedInteger('generation_version')->default(1);
            $table->integer('region_x');
            $table->integer('region_y');
            $table->integer('min_x');
            $table->integer('max_x');
            $table->integer('min_y');
            $table->integer('max_y');
            $table->unique(['world_id', 'code']);
            $table->index(['world_id', 'min_x', 'max_x', 'min_y', 'max_y']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('regions', function (Blueprint $table): void {
                $table->text('boundary')->nullable();
            });

            return;
        }

        DB::statement('ALTER TABLE regions ADD COLUMN boundary geometry(Polygon, 4326) NOT NULL');
        DB::statement('CREATE INDEX regions_boundary_gist ON regions USING GIST (boundary)');
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
