<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ColumnAnalysisService;
use App\Services\MetadataCache;

/** Analisi dei valori di una colonna. La pagina chiede i dati via API (le query possono durare secondi). */
final class AnalysisController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly ColumnAnalysisService $analysis,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('analysis/index', [
            'title'   => 'Analisi colonna',
            'table'   => $request->str('t'),
            'column'  => $request->str('c'),
            'scripts' => ['js/analysis.js'],
        ]);
    }

    public function data(Request $request): Response
    {
        $table = $this->cache->catalog()->require($request->str('t'));
        $column = $table->column($request->str('c')) ?? throw HttpException::notFound('Colonna non trovata: ' . $request->str('c'));
        return $this->ok($this->analysis->analyze($table, $column, $request->bool('full')));
    }
}
