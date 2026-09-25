<?php
declare(strict_types=1);

// Helper globali per le viste. Tenerli pochi e senza logica di dominio.

use App\Core\App;
use App\Core\Settings;

/** Escape HTML: usare SEMPRE nelle viste per ogni valore dinamico. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Percorso base dell'app (es. "/matriosga"), calcolato dal front controller. */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($script, 2)), '/');
    }
    return $base;
}

/** @param array<string, scalar|null> $query */
function url(string $path = '/', array $query = []): string
{
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return base_url() . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $relative): string
{
    $file = BASE_PATH . '/public/assets/' . $relative;
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return base_url() . '/assets/' . $relative . '?v=' . $version;
}

/** @return array<string, mixed> */
function assets_config(): array
{
    static $config = null;
    return $config ??= require BASE_PATH . '/config/assets.php';
}

function setting(string $path, mixed $default = null): mixed
{
    return App::get(Settings::class)->get($path, $default);
}

function fmt_int(int|float|null $n): string
{
    return $n === null ? '—' : number_format((float) $n, 0, ',', '.');
}
