<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;

/** Landing page: logo + ricerca fra le funzioni dell'app (nessuna query al database). */
final class HomeController extends Controller
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function index(Request $request): Response
    {
        return $this->view('home/index', [
            'tools'   => $this->settings->get('menu', []),
            'query'   => $request->str('q'),
            'scripts' => ['js/landing.js'],
        ], 'layout/landing');
    }
}
