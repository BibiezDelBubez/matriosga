<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\EnvironmentService;
use App\Services\MetadataCache;
use Throwable;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly MetadataCache $cache,
        private readonly EnvironmentService $environment,
    ) {
    }

    public function index(Request $request): Response
    {
        $data = [
            'title'      => 'Dashboard',
            'breadcrumb' => [],
            'configured' => $this->settings->isConfigured(),
            'error'      => null,
            'stats'      => null,
            'env'        => $this->environment->summary(),
        ];
        if ($data['configured']) {
            try {
                $catalog = $this->cache->catalog();
                $data['stats'] = $catalog->stats();
                $data['noise'] = $this->noiseFilter($request, $catalog)['counts'];
                $data['server'] = $catalog->server();
                $data['cachedAt'] = $catalog->cachedAt();
            } catch (Throwable $e) {
                $data['error'] = $e->getMessage();
            }
        }
        return $this->view('dashboard/index', $data);
    }
}
