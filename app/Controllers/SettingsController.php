<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\DatabaseService;
use App\Services\EnvironmentService;
use Throwable;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly DatabaseService $db,
        private readonly EnvironmentService $environment,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('settings/index', [
            'title'         => 'Impostazioni',
            'c'             => $this->settings->connection(),
            'limits'        => $this->settings->get('limits'),
            'filters'       => $this->settings->get('filters'),
            'limitDefaults' => $this->settings->defaults('limits'),
            'saved'         => $request->bool('saved'),
            'scripts'       => ['js/settings.js'],
        ]);
    }

    /** Controlli sull'ambiente server + checklist per installare su un'altra macchina. */
    public function environment(Request $request): Response
    {
        return $this->view('settings/environment', [
            'title'      => 'Ambiente server',
            'breadcrumb' => ['Impostazioni' => url('/settings'), 'Ambiente server' => null],
            'checks'     => $this->environment->checks(),
            'paths'      => $this->environment->paths(),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->settings->update('connection', (array) $request->input('connection', []));
        $this->settings->update('limits', (array) $request->input('limits', []));
        $this->settings->update('filters', (array) $request->input('filters', []));

        $password = (string) $request->input('password', '');
        if ($request->bool('clear_password')) {
            $this->settings->setPassword('');
        } elseif ($password !== '') {
            $this->settings->setPassword($password);
        }
        return Response::redirect(url('/settings', ['saved' => 1]));
    }

    /** Prova i valori del form (anche non salvati). Password vuota = usa quella salvata. */
    public function test(Request $request): Response
    {
        $conn = array_replace(
            $this->settings->connection(),
            $this->settings->normalize('connection', (array) $request->input('connection', [])),
        );
        if (trim((string) $conn['server']) === '' || trim((string) $conn['database']) === '') {
            return $this->fail('Indicare almeno server e database.');
        }
        $password = (string) $request->input('password', '');
        if ($password === '' && !$request->bool('clear_password')) {
            $password = $this->settings->password();
        }

        $start = microtime(true);
        try {
            $info = $this->db->serverInfo($this->db->connect($conn, $password));
        } catch (Throwable $e) {
            return $this->fail($e->getMessage());
        }
        $info['ms'] = (int) round((microtime(true) - $start) * 1000);
        return $this->ok($info);
    }
}
