<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response Helper
 * Manages status code, headers, redirects, HTML content, and JSON output.
 */
class Response
{
    private int $statusCode;
    private array $headers = [];
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create an HTML response.
     */
    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/html; charset=UTF-8';
        return new self($content, $status, $headers);
    }

    /**
     * Create a JSON response.
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';
        $content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return new self($content !== false ? $content : '{}', $status, $headers);
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Send headers and output body to the client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }
}
