<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use InvalidArgumentException;

/**
 * Validation Exception
 * Carries an associative array of field-level validation errors without triggering dynamic property deprecation in PHP 8.2+.
 */
class ValidationException extends InvalidArgumentException
{
    /**
     * @var array<string, string>
     */
    public array $errors = [];

    public function __construct(string|array $message = '', array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        if (is_array($message)) {
            $errors = $message;
            $message = !empty($errors) ? (string) reset($errors) : 'Validation failed.';
        }
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }
}
