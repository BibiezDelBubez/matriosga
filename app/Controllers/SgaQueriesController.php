<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Helpers\Pager;
use App\Services\QueryTextService;
use App\Services\SqlModuleService;

/** "Query di SGA": cerca un nome di tabella/colonna dentro viste, funzioni e trigger del gestionale. */
final class SgaQueriesController extends Controller
{
    public function __construct(
        private readonly SqlModuleService $modules,
        private readonly QueryTextService $queryText,
        private readonly Settings $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $query = $request->str('q');
        $whole = $request->bool('word', true);
        $access = $this->modules->access();

        $results = null;
        $pager = null;
        if ($query !== '' && $access['readable'] > 0) {
            $results = $this->modules->search($query, $whole);
            $pager = new Pager($request->int('page', 1, 1), (int) $this->settings->get('limits.page_size', 100), count($results));
            $results = $pager->slice($results);
        }

        return $this->view('queries/index', [
            'title'    => 'Query di SGA',
            'query'    => $query,
            'whole'    => $whole,
            'access'   => $access,
            'login'    => $access['readable'] === 0 ? $this->modules->login() : '',
            'database' => (string) $this->settings->get('connection.database'),
            'results'  => $results,
            'pager'    => $pager,
            'scripts'  => ['js/queries.js'],
        ]);
    }

    /** Testo completo di una query di SGA + come usarla in Power BI (se è una vista o una funzione tabella). */
    public function show(Request $request): Response
    {
        $module = $this->modules->get($request->str('n'));
        $usage = SqlModuleService::usageSql($module);
        return $this->ok($module + [
            'label' => SqlModuleService::TYPES[$module['type']][0] ?? $module['type'],
            'usage' => $usage,
            'pq'    => $usage !== null ? $this->queryText->powerQueryNative($usage) : null,
        ]);
    }
}
