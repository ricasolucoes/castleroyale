<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Exception;

use RuntimeException;

/**
 * Base for every rule violation raised by the domain layer.
 *
 * Domain exceptions never carry HTTP concerns. The interface layer maps them
 * to an {@see \Game\Shared\Application\Error\ErrorCode} and a status code.
 */
abstract class DomainException extends RuntimeException {}
