<?php
declare(strict_types=1);

// Autoloader PSR-4 minimale: App\Foo\Bar -> app/Foo/Bar.php (nessun Composer richiesto).
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
