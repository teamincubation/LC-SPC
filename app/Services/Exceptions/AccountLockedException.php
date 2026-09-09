<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Account Locked Exception
 * Thrown when an account is temporarily locked due to excessive failed attempts.
 */
class AccountLockedException extends RuntimeException
{
}
