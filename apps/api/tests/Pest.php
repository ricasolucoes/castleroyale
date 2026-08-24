<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrapping
|--------------------------------------------------------------------------
|
| Feature tests get the full application and a fresh database. Unit tests
| exercise the pure domain and must not need either — if a test under
| tests/Unit starts needing the container, it belongs in Feature.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Architecture');

/*
|--------------------------------------------------------------------------
| Custom expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeApiSuccess', function (int $status = 200) {
    /** @var Illuminate\Testing\TestResponse $this */
    $this->value->assertStatus($status)->assertJsonStructure(['data']);

    return $this;
});
