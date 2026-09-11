<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Certificate Domain Exception
 * Carries an explicit HTTP status code and optional structured error details.
 */
class CertificateException extends RuntimeException
{
    private int $statusCode;
    private array $details;

    public function __construct(string $message = '', int $statusCode = 400, array $details = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
