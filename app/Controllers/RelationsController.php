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
use App\Services\UserRelationService;

final class RelationsController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly QueryTextService $queryText,
        private readonly RelationshipService $relations,
        private readonly UserRelationService $userRelations,
    ) {
    }

    public function index(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        $kind = $request->enum('kind', ['declared', 'candidates', 'manual'], 'declared');
        $query = $request->str('q');
        $from = $request->str('from') !== '' ? $catalog->require($request->str('from'))->fullName : null;
        $to = $request->str('to') !== '' ? $catalog->require($request->str('to'))->fullName : null;
        $hubs = $request->bool('hubs');
        $noise = $this->noiseFilter($request, $catalog);

        $result = null;
        $pager = null;
        $joins = [];
        if ($query !== '' || $from !== null || $to !== null || $kind !== 'declared') {
            $source = match ($kind) {
                'candidates' => $this->relations->candidatesFor($catalog, $hubs),
                'manual'     => $this->relations->manual($catalog),
                default      => $this->relations->declared($catalog, $from, $to),
            };
            $result = $this->relations->filter($this->relations->withoutTables($source, $noise['hidden']), $query, $from, $to);
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
            'noise'   => $noise,
            'result'  => $result,
            'joins'   => $joins,
            'pager'   => $pager,
            'total'   => count($catalog->fks()),
            'manualCount' => count($this->relations->manual($catalog)),
            'scripts' => ['js/relation-editor.js'],
            'hubList' => $result === null ? $this->relations->hubs($catalog, 24) : [],
        ]);
    }

    /** Salva una relazione definita dall'utente. */
    public function addUser(Request $request): Response
    {
        return $this->ok($this->userRelations->add(
            $this->cache->catalog(),
            $request->str('from'),
            $request->list('from_cols'),
            $request->str('to'),
            $request->list('to_cols'),
            $request->str('note'),
        ));
    }

    public function deleteUser(Request $request): Response
    {
        $this->userRelations->delete($request->str('id'));
        return $this->ok(null);
    }

    /** Coppie di colonne proposte per collegare due tabelle (editor delle relazioni). */
    public function suggest(Request $request): Response
    {
        return $this->ok($this->relations->suggestPairs($this->cache->catalog(), $request->str('from'), $request->str('to')));
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
