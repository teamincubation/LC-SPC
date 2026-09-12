<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request Abstraction
 * Encapsulates method, URI, path, headers, query parameters, POST body, and JSON payloads.
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $post;
    private array $files;
    private array $server;
    private array $headers;
    private ?array $json = null;

    public function __construct(
        array $query = [],
        array $post = [],
        array $files = [],
        array $server = []
    ) {
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;

        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->server['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        $this->headers = $this->extractHeaders($this->server);
    }

    /**
     * Capture the current global HTTP request.
     */
    public static function capture(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get a query string parameter.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * Get a POST parameter.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    /**
     * Get parsed JSON body.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $rawBody = file_get_contents('php://input');
            $decoded = json_decode($rawBody ?: '', true);
            $this->json = is_array($decoded) ? $decoded : [];
        }

        if ($key === null) {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    /**
     * Get parameter checking JSON, POST, and Query in order.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }

        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }

        return $default;
    }

    /**
     * Return all input parameters merged.
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?: []);
    }

    /**
     * Retrieve an HTTP request header.
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $key));
        return $this->headers[$normalized] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if request was made via HTTPS.
     */
    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && strtolower($this->server['HTTPS']) !== 'off')
            || ($this->server['SERVER_PORT'] ?? '') == 443
            || strtolower($this->header('x-forwarded-proto', '')) === 'https';
    }

    /**
     * Check if request expects a JSON response.
     */
    public function expectsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    /**
     * Retrieve client IP address securely.
     */
    public function ip(): string
    {
        $ipSources = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipSources as $source) {
            if (!empty($this->server[$source])) {
                $ips = explode(',', $this->server[$source]);
                $candidate = trim($ips[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return '127.0.0.1';
    }

    public function getIp(): string
    {
        return $this->ip();
    }

    /**
     * Retrieve client user agent.
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Extract normalized headers from $_SERVER.
     */
    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }
}
