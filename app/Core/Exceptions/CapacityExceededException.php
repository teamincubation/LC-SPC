<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Thrown when an action would cause an event to exceed its capped confirmed capacity.
 */
class CapacityExceededException extends RuntimeException
{
}
