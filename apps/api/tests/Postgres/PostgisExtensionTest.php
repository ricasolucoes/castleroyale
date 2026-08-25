<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('runs against real postgresql, not sqlite', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
})->group('postgres');

it('has the postgis extension enabled', function (): void {
    $row = DB::selectOne('select postgis_version() as version');

    expect($row?->version)->toBeString()->not->toBeEmpty();
})->group('postgres');

it('applied the extension migration before every other migration', function (): void {
    $first = DB::table('migrations')->orderBy('id')->value('migration');

    expect($first)->toBe('0000_01_01_000000_enable_postgis_extension');
})->group('postgres');

it('creates a geometry column with a gist index', function (): void {
    Schema::create('postgis_probes', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->geometry('boundary', 'polygon', 4326);
    });
    DB::statement('CREATE INDEX postgis_probes_boundary_gist ON postgis_probes USING GIST (boundary)');

    $indexes = DB::select("select indexdef from pg_indexes where tablename = 'postgis_probes'");
    $definitions = implode("\n", array_map(static fn (object $i): string => (string) $i->indexdef, $indexes));

    expect($definitions)->toContain('USING gist');

    Schema::dropIfExists('postgis_probes');
})->group('postgres');

it('round-trips a 26 character ulid primary key', function (): void {
    Schema::create('ulid_probes', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('label');
    });

    $id = (string) Str::ulid();
    DB::table('ulid_probes')->insert(['id' => $id, 'label' => 'probe']);

    expect(DB::table('ulid_probes')->where('id', $id)->value('label'))->toBe('probe');

    Schema::dropIfExists('ulid_probes');
})->group('postgres');
