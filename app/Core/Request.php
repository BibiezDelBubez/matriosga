<?php
declare(strict_types=1);

namespace App\Core;

/** Input HTTP normalizzato + validazioni base riusabili (stringhe, interi, enum). */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly bool $jsonAccepted,
    ) {
    }

    public static function capture(): self
    {
        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = base_url();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/public/') || $uri === '/public') {
            $uri = substr($uri, 7);
        }
        $path = '/' . trim($uri, '/');

        $body = $_POST;
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $body,
            str_starts_with($path, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function wantsJson(): bool
    {
        return $this->jsonAccepted;
    }

    /** Valore grezzo: prima body (POST/JSON), poi query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $value = filter_var($this->input($key), FILTER_VALIDATE_INT);
        return $value === false ? $default : max($min, min($max, $value));
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Valore ammesso solo se presente nell'elenco, altrimenti il default.
     * @param list<string> $allowed
     */
    public function enum(string $key, array $allowed, string $default): string
    {
        $value = $this->str($key, $default);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? array_values(array_filter(array_map('strval', $value), 'strlen')) : [];
    }
}
