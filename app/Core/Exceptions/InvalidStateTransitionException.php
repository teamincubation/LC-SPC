<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use DomainException;

/**
 * Thrown when an invalid registration lifecycle state transition is attempted.
 */
class InvalidStateTransitionException extends DomainException
{
}
