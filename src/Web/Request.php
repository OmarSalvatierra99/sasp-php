<?php

declare(strict_types=1);

namespace Sasp\Web;

/**
 * Lightweight HTTP request wrapper to keep controllers/service
 * layers agnostic of PHP superglobals.
 */
class Request
{
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $body;
    /** @var array<string, mixed> */
    private array $files;
    /** @var array<string, mixed> */
    private array $server;
    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, mixed> $session
     */
    public function __construct(
        array $query = [],
        array $body = [],
        array $files = [],
        array $server = [],
        array $session = []
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
        $this->session = $session;
    }

    public static function fromGlobals(array $session = []): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER, $session);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return (string)($this->server['REQUEST_URI'] ?? '/');
    }

    public function path(): string
    {
        return parse_url($this->uri(), PHP_URL_PATH) ?: '/';
    }

    public function isAjax(): bool
    {
        $requested = $this->server['HTTP_X_REQUESTED_WITH'] ?? '';
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return strcasecmp($requested, 'XMLHttpRequest') === 0
            || str_contains(strtolower($contentType), 'application/json');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string)$this->server[$key] : $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allInput(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    public function rawBody(): string
    {
        return (string)file_get_contents('php://input');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonBody(): array
    {
        $payload = $this->rawBody();
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function session(string $key, mixed $default = null): mixed
    {
        return $this->session[$key] ?? $default;
    }
}
