<?php
declare(strict_types=1);

namespace App\Core;

/** Router a percorsi esatti. Le rotte sono dichiarate solo in routes/web.php. */
final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = [];

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;
        if ($handler === null) {
            throw HttpException::notFound('Pagina non trovata: ' . $request->path());
        }
        [$class, $method] = $handler;
        return App::get($class)->{$method}($request);
    }
}
