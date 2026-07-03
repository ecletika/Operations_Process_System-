<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $body;
    private array $server;

    public function __construct()
    {
        $this->query = $_GET;
        $this->server = $_SERVER;

        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $this->body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
        } else {
            $this->body = $_POST;
        }
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return rtrim($path, '/') ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Lê o header "Authorization: Bearer <token>" (Fase 5 - API REST).
     * Cobre tanto mod_php como CGI/FastCGI (onde o Apache costuma renomear
     * o header para REDIRECT_HTTP_AUTHORIZATION).
     */
    public function bearerToken(): ?string
    {
        $header = $this->server['HTTP_AUTHORIZATION']
            ?? $this->server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($header === null && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }

        if ($header === null || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
