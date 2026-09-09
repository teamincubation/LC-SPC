<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Authorization Exception
 * Thrown when an authenticated user attempts an action exceeding their granted role rank.
 */
class AuthorizationException extends RuntimeException
{
}
