<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Sql;
use App\Models\Catalog;
use App\Services\MetadataCache;
use App\Services\ValueSearchService;

/** Ricerca di un valore in tutto il database (pagina + API a lotti). */
final class SearchController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly ValueSearchService $search,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('search/index', [
            'title'   => 'Cerca valore',
            'value'   => $request->str('q'),
            'mode'    => $request->enum('mode', array_keys(Sql::MATCH_MODES), 'contains'),
            'modes'   => Sql::MATCH_MODES,
            'maxRows' => (int) setting('limits.search_max_table_rows'),
            'scripts' => ['js/search.js'],
        ]);
    }

    public function plan(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        return $this->ok($this->search->plan($catalog, $this->criteria($request, $catalog), $request->bool('force')));
    }

    public function run(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        $tables = array_slice($request->list('tables'), 0, 200);
        return $this->ok($this->search->run($catalog, $this->criteria($request, $catalog), $tables));
    }

    public function rows(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        return $this->ok($this->search->rows($catalog, $this->criteria($request, $catalog), $request->str('t'), $request->str('c')));
    }

    /** @return array<string, mixed> */
    private function criteria(Request $request, Catalog $catalog): array
    {
        return $this->search->criteria(
            $catalog,
            $request->str('value'),
            $request->enum('mode', array_keys(Sql::MATCH_MODES), 'contains'),
            $request->bool('numbers'),
            $request->bool('views'),
        );
    }
}
