<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MetadataCache;

final class MetadataController extends Controller
{
    public function __construct(private readonly MetadataCache $cache)
    {
    }

    public function refresh(Request $request): Response
    {
        $start = microtime(true);
        $catalog = $this->cache->refresh();
        return $this->ok([
            'tables'  => count($catalog->objects()),
            'seconds' => round(microtime(true) - $start, 1),
        ]);
    }
}
