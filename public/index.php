<?php
declare(strict_types=1);

// Front controller: unico punto di ingresso dell'applicazione.
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoload.php';
require BASE_PATH . '/app/Helpers/functions.php';

(new App\Core\App())->run();
