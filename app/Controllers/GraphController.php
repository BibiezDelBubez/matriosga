<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\GraphService;
use App\Services\MetadataCache;

/** Grafo interattivo delle relazioni (Cytoscape.js locale). */
final class GraphController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly GraphService $graph,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('graph/index', [
            'title'   => 'Grafo',
            'table'   => $request->str('t'),
            'depth'   => $request->int('depth', 1, 1, 3),
            'cand'    => $request->bool('cand'),
            'hubs'    => $request->bool('hubs'),
            'path'    => $request->str('path'),
            'nodes'   => $request->str('nodes'),
            'scripts' => ['cytoscape', 'js/graph.js'],
        ]);
    }

    /** Vicinato di una tabella; 'extra' = nodi già nel grafo del browser (per collegarli ai nuovi). */
    public function data(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        $center = $catalog->require($request->str('t'))->fullName;
        $extra = array_values(array_filter(array_map(static fn (string $n) => $catalog->resolve($n), array_slice($request->list('extra'), 0, 500))));
        return $this->ok($this->graph->neighborhood(
            $catalog,
            $center,
            $request->int('depth', 1, 1, 3),
            $request->bool('cand'),
            $request->bool('hubs'),
            (int) setting('limits.graph_max_nodes', 150),
            $extra,
        ));
    }
}
