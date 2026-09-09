<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Authentication Exception
 * Thrown when credentials fail verification or account status prevents login.
 */
class AuthenticationException extends RuntimeException
{
}
