<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\MetadataCache;
use App\Services\QueryBuilderService;
use App\Services\QueryTextService;

/** Costruttore di query multi-tabella. La pagina tiene lo stato; le API collegano, generano SQL e anteprima. */
final class BuilderController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly QueryBuilderService $builder,
        private readonly QueryTextService $queryText,
        private readonly Settings $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('builder/index', [
            'title'     => 'Costruttore di query',
            'operators' => QueryBuilderService::OPERATORS,
            'noise'     => $this->noiseFilter($request, $this->cache->catalog()),
            'scripts'   => ['js/relation-editor.js', 'js/builder.js'],
        ]);
    }

    /** Percorsi per collegare una tabella nuova a quelle già scelte. */
    public function connect(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        return $this->ok($this->builder->connect($catalog, $request->str('table'), $request->list('existing'), [
            'depth'      => (int) $this->settings->get('limits.path_max_depth', 4),
            'max'        => 6,
            'candidates' => $request->bool('cand'),
            'minScore'   => 80,
            'hubs'       => $request->bool('hubs'),
            'avoid'      => [],
            'hidden'     => $this->noiseFilter($request, $catalog)['hidden'],
        ]));
    }

    /** SQL e Power Query da copiare (valori scritti come letterali). */
    public function sql(Request $request): Response
    {
        $sql = $this->builder->build($this->cache->catalog(), (array) $request->input('spec', []), true)['sql'];
        return $this->ok(['sql' => $sql, 'pq' => $this->queryText->powerQueryNative($sql)]);
    }

    public function preview(Request $request): Response
    {
        return $this->ok($this->builder->preview($this->cache->catalog(), (array) $request->input('spec', [])));
    }
}
