<?php
declare(strict_types=1);

use App\Controllers\AnalysisController;
use App\Controllers\BuilderController;
use App\Controllers\ColumnsController;
use App\Controllers\DashboardController;
use App\Controllers\PathController;
use App\Controllers\RelationsController;
use App\Controllers\SearchController;
use App\Controllers\SgaQueriesController;
use App\Controllers\SpyController;
use App\Controllers\TablesController;
use App\Controllers\GraphController;
use App\Controllers\HomeController;
use App\Controllers\MetadataController;
use App\Controllers\SettingsController;
use App\Core\Router;

// Unico elenco delle rotte. Le /api/* rispondono sempre JSON.
return static function (Router $r): void {
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/dashboard', [DashboardController::class, 'index']);

    $r->get('/tables', [TablesController::class, 'index']);
    $r->get('/tables/show', [TablesController::class, 'show']);
    $r->get('/api/tables/preview', [TablesController::class, 'preview']);
    $r->get('/api/tables/names', [TablesController::class, 'names']);
    $r->get('/api/tables/columns', [TablesController::class, 'columns']);
    $r->get('/analysis', [AnalysisController::class, 'index']);
    $r->get('/api/analysis', [AnalysisController::class, 'data']);
    $r->get('/path', [PathController::class, 'index']);
    $r->get('/builder', [BuilderController::class, 'index']);
    $r->post('/api/builder/connect', [BuilderController::class, 'connect']);
    $r->post('/api/builder/sql', [BuilderController::class, 'sql']);
    $r->post('/api/builder/preview', [BuilderController::class, 'preview']);
    $r->get('/spy', [SpyController::class, 'index']);
    $r->get('/api/spy/state', [SpyController::class, 'state']);
    $r->post('/api/spy/start', [SpyController::class, 'start']);
    $r->post('/api/spy/stop', [SpyController::class, 'stop']);
    $r->post('/api/spy/scan', [SpyController::class, 'scan']);
    $r->post('/api/spy/rows', [SpyController::class, 'rows']);
    $r->post('/api/spy/reset', [SpyController::class, 'reset']);
    $r->get('/queries', [SgaQueriesController::class, 'index']);
    $r->get('/api/queries/show', [SgaQueriesController::class, 'show']);
    $r->get('/graph', [GraphController::class, 'index']);
    $r->post('/api/graph', [GraphController::class, 'data']);
    $r->get('/columns', [ColumnsController::class, 'index']);
    $r->get('/search', [SearchController::class, 'index']);
    $r->post('/api/search/plan', [SearchController::class, 'plan']);
    $r->post('/api/search/run', [SearchController::class, 'run']);
    $r->post('/api/search/rows', [SearchController::class, 'rows']);
    $r->get('/relations', [RelationsController::class, 'index']);
    $r->post('/api/relations/verify', [RelationsController::class, 'verify']);
    $r->post('/api/relations/user', [RelationsController::class, 'addUser']);
    $r->post('/api/relations/user/delete', [RelationsController::class, 'deleteUser']);
    $r->get('/api/relations/suggest', [RelationsController::class, 'suggest']);

    $r->get('/settings', [SettingsController::class, 'index']);
    $r->post('/settings', [SettingsController::class, 'save']);
    $r->get('/settings/environment', [SettingsController::class, 'environment']);
    $r->post('/api/settings/test', [SettingsController::class, 'test']);
    $r->post('/api/metadata/refresh', [MetadataController::class, 'refresh']);
};
