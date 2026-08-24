<?php

declare(strict_types=1);

namespace Game\Shared\Application\Error;

use RuntimeException;
use Throwable;

/**
 * An application-level failure that is safe to show to a player.
 *
 * Carries a stable {@see ErrorCode} plus optional structured details. The
 * message is for logs and developers; the client renders its own copy keyed
 * by the code.
 */
final class GameException extends RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message = '',
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode->value, 0, $previous);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function of(ErrorCode $code, string $message = '', array $details = []): self
    {
        return new self($code, $message, $details);
    }

    public function httpStatus(): int
    {
        return $this->errorCode->httpStatus();
    }
}
