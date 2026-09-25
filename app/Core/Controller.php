<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\MetadataCache;

/** Base dei controller: solo helper di presentazione, nessuna logica di dominio. */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], string $layout = 'layout/main'): Response
    {
        // Dati comuni al layout (voce di menu attiva, badge connessione in topbar).
        $data += [
            'currentPath' => App::get(Request::class)->path(),
            'conn'        => App::get(Settings::class)->connection(),
            'meta'        => App::get(MetadataCache::class)->status(),
        ];
        return Response::html((new View())->render($template, $data, $layout));
    }

    protected function ok(mixed $data): Response
    {
        return Response::json(['ok' => true, 'data' => $data]);
    }

    protected function fail(string $error, int $status = 400): Response
    {
        return Response::json(['ok' => false, 'error' => $error], $status);
    }
}
