<?php

declare(strict_types=1);

use Game\Shared\Infrastructure\Database\GameTable;
use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('convention_probes', function (Blueprint $table): void {
        GameTable::entity($table);
        GameTable::worldScoped($table);
        GameTable::timed($table);
        $table->string('label');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('convention_probes');
});

it('gives a game entity a ulid primary key and utc timestamps', function (): void {
    expect(Schema::hasColumns('convention_probes', ['id', 'created_at', 'updated_at']))->toBeTrue();
});

it('gives every gameplay table a world_id', function (): void {
    expect(Schema::hasColumn('convention_probes', 'world_id'))->toBeTrue();
});

it('gives a timed operation the started/finishes/completed triple', function (): void {
    expect(Schema::hasColumns('convention_probes', ['started_at', 'finishes_at', 'completed_at']))->toBeTrue();
});

it('generates a 26 character ulid primary key on create', function (): void {
    $model = new class extends Model
    {
        use HasGameUlid;

        public $timestamps = true;

        protected $table = 'convention_probes';

        protected $fillable = ['world_id', 'label'];
    };

    $saved = $model->newInstance(['world_id' => (string) Illuminate\Support\Str::ulid(), 'label' => 'probe']);
    $saved->save();

    expect($saved->getKey())->toBeString()
        ->and(strlen((string) $saved->getKey()))->toBe(26)
        ->and($saved->getKeyType())->toBe('string')
        ->and($saved->getIncrementing())->toBeFalse();
});
