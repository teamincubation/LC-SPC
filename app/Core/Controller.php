<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Base Controller
 * Provides shorthand methods for rendering views, JSON responses, redirects, and input validation.
 */
abstract class Controller
{
    /**
     * Render an HTML view inside a response.
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/main', int $status = 200): Response
    {
        $html = View::render($view, $data, $layout);
        return Response::html($html, $status);
    }

    /**
     * Return a JSON response.
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Redirect to an application path (prepended with base path if relative).
     */
    protected function redirect(string $path, int $status = 302): Response
    {
        $target = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : url($path);

        return Response::redirect($target, $status);
    }

    /**
     * Basic input validation helper.
     * Rules format: ['field' => 'required|email|min:3|max:100']
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && (empty($value) && $value !== '0' && $value !== 0)) {
                    $errors[$field][] = "The {$field} field is required.";
                }

                if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                }

                if (str_starts_with($rule, 'min:') && !empty($value)) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                }

                if (str_starts_with($rule, 'max:') && !empty($value)) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) > $max) {
                        $errors[$field][] = "The {$field} may not exceed {$max} characters.";
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors));
        }

        return $data;
    }
}
