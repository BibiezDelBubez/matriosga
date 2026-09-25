<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Pager;
use App\Services\MetadataCache;
use App\Services\QueryTextService;
use App\Services\RelationshipService;

final class RelationsController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly QueryTextService $queryText,
        private readonly RelationshipService $relations,
    ) {
    }

    public function index(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        $kind = $request->enum('kind', ['declared', 'candidates'], 'declared');
        $query = $request->str('q');
        $from = $request->str('from') !== '' ? $catalog->require($request->str('from'))->fullName : null;
        $to = $request->str('to') !== '' ? $catalog->require($request->str('to'))->fullName : null;
        $hubs = $request->bool('hubs');

        $result = null;
        $pager = null;
        $joins = [];
        if ($query !== '' || $from !== null || $to !== null || $kind === 'candidates') {
            $source = $kind === 'candidates'
                ? $this->relations->candidatesFor($catalog, $hubs)
                : $this->relations->declared($catalog, $from, $to);
            $result = $this->relations->filter($source, $query, $from, $to);
            $pager = new Pager($request->int('page', 1, 1), (int) setting('limits.page_size', 100), $result['total']);
            $result['rows'] = $pager->slice($result['rows']);
            $joins = $this->queryText->joins($catalog, $result['rows']); // solo per la pagina mostrata
        }

        return $this->view('relations/index', [
            'title'   => 'Relazioni',
            'kind'    => $kind,
            'query'   => $query,
            'from'    => $from,
            'to'      => $to,
            'hubs'    => $hubs,
            'result'  => $result,
            'joins'   => $joins,
            'pager'   => $pager,
            'total'   => count($catalog->fks()),
            'hubList' => $result === null ? $this->relations->hubs($catalog, 24) : [],
        ]);
    }

    /** Verifica sui dati di una relazione (candidata o dichiarata): % di valori che trovano corrispondenza. */
    public function verify(Request $request): Response
    {
        return $this->ok($this->relations->verify(
            $this->cache->catalog(),
            $request->str('from'),
            $request->list('from_cols'),
            $request->str('to'),
            $request->list('to_cols'),
        ));
    }
}
