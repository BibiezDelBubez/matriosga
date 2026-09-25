<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MetadataCache;
use App\Services\QueryTextService;
use App\Services\RelationshipService;
use App\Services\TableDataService;

final class TablesController extends Controller
{
    /** FK entranti e candidate mostrate nel dettaglio tabella (il resto si cerca in Relazioni). */
    private const MAX_RELATIONS = 50;

    public function __construct(
        private readonly MetadataCache $cache,
        private readonly QueryTextService $queryText,
        private readonly TableDataService $data,
        private readonly RelationshipService $relations,
    ) {
    }

    public function index(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        return $this->view('tables/index', [
            'title'   => 'Tabelle',
            'tables'  => $catalog->summaries(),
            'stats'   => $catalog->stats(),
            'schemas' => array_keys($catalog->stats()['schemas']),
            'query'   => $request->str('q'),
            'scripts' => ['js/tables.js'],
        ]);
    }

    public function show(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        $table = $catalog->require($request->str('t'));
        $selected = $table->pickColumns($request->list('cols'));

        $rel = $this->relations->forTable($catalog, $table->fullName, self::MAX_RELATIONS);
        $sql = $this->queryText->select($table, $selected ?: null);

        return $this->view('tables/show', [
            'title'      => $table->fullName,
            'breadcrumb' => ['Tabelle' => url('/tables'), $table->fullName => null],
            'table'      => $table,
            'selected'   => array_map(static fn ($c) => $c->name, $selected),
            'joinsOut'   => $this->queryText->joins($catalog, $table->fksOut()),
            'fksIn'      => $rel['fksIn'],
            'fksInTotal' => $rel['fksInTotal'],
            'joinsIn'    => $this->queryText->joins($catalog, $rel['fksIn']),
            'candOut'    => $rel['candOut'] + ['joins' => $this->queryText->joins($catalog, $rel['candOut']['rows'])],
            'candIn'     => $rel['candIn'] + ['joins' => $this->queryText->joins($catalog, $rel['candIn']['rows'])],
            'code'       => [
                'sql'      => $sql,
                'pqTable'  => $this->queryText->powerQueryTable($table, $selected ?: null),
                'pqNative' => $this->queryText->powerQueryNative($sql),
            ],
            'tab'        => $request->enum('tab', ['columns', 'keys', 'indexes', 'data', 'code'], 'columns'),
            'scripts'    => ['js/table.js'],
        ]);
    }

    /** Nomi di tutte le tabelle/viste (per l'autocompletamento dei campi tabella). */
    public function names(Request $request): Response
    {
        return $this->ok(array_keys($this->cache->catalog()->objects()));
    }

    /** Colonne di una tabella (per i campi di scelta colonna). */
    public function columns(Request $request): Response
    {
        $table = $this->cache->catalog()->require($request->str('t'));
        return $this->ok([
            'table'   => $table->fullName,
            'columns' => array_map(static fn ($c) => ['name' => $c->name, 'type' => $c->typeLabel(), 'category' => $c->category()], $table->columns()),
        ]);
    }

    /** Anteprima righe (API JSON, caricata solo quando si apre la scheda). */
    public function preview(Request $request): Response
    {
        $table = $this->cache->catalog()->require($request->str('t'));
        return $this->ok($this->data->rows($table));
    }
}
