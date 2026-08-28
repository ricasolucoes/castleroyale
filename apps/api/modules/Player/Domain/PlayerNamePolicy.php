<?php

declare(strict_types=1);

namespace Game\Player\Domain;

use Normalizer;

final readonly class PlayerNamePolicy
{
    /**
     * @param list<string> $deniedNames
     */
    public function __construct(
        private int $minimumLength,
        private int $maximumLength,
        private array $deniedNames,
    ) {}

    public function normalise(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name));
        $normalised = is_string($collapsed) ? $collapsed : trim($name);

        if (class_exists(Normalizer::class)) {
            $unicodeNormalised = Normalizer::normalize($normalised, Normalizer::FORM_C);
            if (is_string($unicodeNormalised)) {
                $normalised = $unicodeNormalised;
            }
        }

        return $normalised;
    }

    public function accepts(string $name): bool
    {
        $normalised = $this->normalise($name);
        $length = mb_strlen($normalised);

        if ($length < $this->minimumLength || $length > $this->maximumLength) {
            return false;
        }

        if (preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]*$/u', $normalised) !== 1) {
            return false;
        }

        $folded = mb_strtolower($normalised, 'UTF-8');

        return ! in_array($folded, array_map(
            static fn (string $denied): string => mb_strtolower(trim($denied), 'UTF-8'),
            $this->deniedNames,
        ), true);
    }
}
