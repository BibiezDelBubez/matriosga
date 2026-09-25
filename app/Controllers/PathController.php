<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\MetadataCache;
use App\Services\PathFinderService;
use App\Services\QueryTextService;

/** Percorso tra due tabelle. */
final class PathController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly PathFinderService $finder,
        private readonly QueryTextService $queryText,
        private readonly Settings $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $catalog = $this->cache->catalog();
        $opt = [
            'depth'      => $request->int('depth', (int) $this->settings->get('limits.path_max_depth'), 1, 8),
            'max'        => (int) $this->settings->get('limits.path_max_results'),
            'candidates' => $request->bool('cand'),
            'minScore'   => $request->int('score', 80, 50, 100),
            'hubs'       => $request->bool('hubs'),
            'avoid'      => array_values(array_filter(array_map(
                static fn (string $n) => $catalog->resolve($n),
                preg_split('/[\s,;]+/', $request->str('avoid')) ?: [],
            ))),
        ];
        $from = $request->str('from');
        $to = $request->str('to');

        $result = null;
        $sql = [];
        if ($from !== '' && $to !== '') {
            [$from, $to] = [$catalog->require($from)->fullName, $catalog->require($to)->fullName];
            $result = $this->finder->find($catalog, $from, $to, $opt);
            $sql = array_map(fn (array $path) => $this->queryText->joinPath($catalog, $path['steps']), $result['paths']);
        }

        return $this->view('path/index', [
            'title'  => 'Percorso',
            'from'   => $from,
            'to'     => $to,
            'opt'    => $opt,
            'avoid'  => $request->str('avoid'),
            'result' => $result,
            'sql'    => $sql,
            'pq'     => array_map(fn (string $s) => $this->queryText->powerQueryNative($s), $sql),
        ]);
    }
}
