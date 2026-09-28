<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Catalog;
use App\Services\MetadataCache;
use App\Services\TableFilterService;

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

    /**
     * Filtro tabelle vuote/copie per la richiesta: ?all=1 (interruttore "mostra anche…") le include.
     * @return array{all: bool, hidden: array<string, true>, counts: array{empty: int, copies: int, hidden: int}}
     */
    protected function noiseFilter(Request $request, Catalog $catalog): array
    {
        $filter = App::get(TableFilterService::class);
        $all = $request->bool('all');
        return ['all' => $all, 'hidden' => $filter->hidden($catalog, $all), 'counts' => $filter->counts($catalog)];
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
