<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MetadataCache;
use App\Services\SpyService;

/** Spia modifiche: la pagina guida l'utente; tutto il lavoro passa dalle API (stato in storage/spy.json). */
final class SpyController extends Controller
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly SpyService $spy,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('spy/index', ['title' => 'Spia modifiche', 'scripts' => ['js/spy.js']]);
    }

    public function state(Request $request): Response
    {
        return $this->ok($this->spy->state());
    }

    public function start(Request $request): Response
    {
        return $this->ok($this->spy->start($request->str('user')));
    }

    public function stop(Request $request): Response
    {
        return $this->ok($this->spy->stop($this->cache->catalog()));
    }

    public function scan(Request $request): Response
    {
        return $this->ok($this->spy->scan($this->cache->catalog(), array_slice($request->list('tables'), 0, 200)));
    }

    public function rows(Request $request): Response
    {
        return $this->ok($this->spy->rows($this->cache->catalog(), $request->str('t')));
    }

    public function reset(Request $request): Response
    {
        $this->spy->reset();
        return $this->ok(null);
    }
}
