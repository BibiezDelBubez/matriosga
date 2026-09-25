<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Pager;
use App\Helpers\Sql;
use App\Models\Column;
use App\Services\ColumnSearchService;
use App\Services\MetadataCache;

final class ColumnsController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly ColumnSearchService $search,
    ) {
    }

    public function index(Request $request): Response
    {
        $query = $request->str('q');
        $mode = $request->enum('mode', array_keys(Sql::MATCH_MODES), 'contains');
        $category = $request->enum('cat', array_keys(Column::CATEGORY_LABELS), '');
        $views = $request->bool('views', true);

        $result = $query === '' ? null : $this->search->search($this->cache->catalog(), $query, $mode, $category, $views);
        $pager = null;
        if ($result !== null) {
            $pager = new Pager($request->int('page', 1, 1), (int) setting('limits.page_size', 100), count($result['rows']));
            $result['rows'] = $pager->slice($result['rows']);
        }

        return $this->view('columns/index', [
            'title'    => 'Colonne',
            'query'    => $query,
            'mode'     => $mode,
            'category' => $category,
            'views'    => $views,
            'result'     => $result,
            'pager'      => $pager,
            'modes'      => Sql::MATCH_MODES,
            'categories' => Column::CATEGORY_LABELS,
            'maxMatches' => ColumnSearchService::MAX_MATCHES,
        ]);
    }
}
