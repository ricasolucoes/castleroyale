<?php

declare(strict_types=1);

use Game\Shared\Domain\Economy\ResourceAmount;
use Game\Shared\Domain\Exception\InvalidResourceAmount;

it('rejects negative amounts', function (): void {
    ResourceAmount::of(-1);
})->throws(InvalidResourceAmount::class);

it('rejects amounts above the ceiling', function (): void {
    ResourceAmount::of(ResourceAmount::MAX + 1);
})->throws(InvalidResourceAmount::class);

it('adds without overflowing', function (): void {
    $sum = ResourceAmount::of(1_000)->plus(ResourceAmount::of(2_500));

    expect($sum->value)->toBe(3_500);
});

it('refuses an addition that would breach the ceiling', function (): void {
    ResourceAmount::of(ResourceAmount::MAX)->plus(ResourceAmount::of(1));
})->throws(InvalidResourceAmount::class);

it('refuses a subtraction that would go negative', function (): void {
    ResourceAmount::of(10)->minus(ResourceAmount::of(11));
})->throws(InvalidResourceAmount::class);

it('saturates at zero when explicitly asked to', function (): void {
    expect(ResourceAmount::of(10)->subtractSaturating(ResourceAmount::of(999))->value)->toBe(0);
});

it('scales by permille and always truncates downward', function (): void {
    // 999 * 1.5 = 1498.5 -> truncated to 1498, never rounded up to 1499.
    expect(ResourceAmount::of(999)->scaledByPermille(1_500)->value)->toBe(1_498);
    expect(ResourceAmount::of(1)->scaledByPermille(999)->value)->toBe(0);
});

it('is immutable across arithmetic', function (): void {
    $original = ResourceAmount::of(500);
    $original->plus(ResourceAmount::of(100));

    expect($original->value)->toBe(500);
});

it('treats equal values as equal', function (): void {
    expect(ResourceAmount::of(7)->equals(ResourceAmount::of(7)))->toBeTrue()
        ->and(ResourceAmount::of(7)->equals(ResourceAmount::of(8)))->toBeFalse();
});
