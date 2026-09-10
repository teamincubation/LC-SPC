<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Thrown when unique registration pass code generation fails after maximum retries.
 */
class RegistrationCodeGenerationException extends RuntimeException
{
}
