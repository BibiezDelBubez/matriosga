<?php
declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use Throwable;

/**
 * Bootstrap dell'applicazione + piccolo container di servizi (singleton per request,
 * dipendenze del costruttore risolte automaticamente). Così nessun controller crea
 * a mano connessioni o servizi.
 */
final class App
{
    /** @var array<class-string, object> */
    private static array $instances = [];

    public function run(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        $request = Request::capture();
        self::$instances[Request::class] = $request;

        try {
            $this->assertSameOrigin($request);
            $router = new Router();
            (require BASE_PATH . '/routes/web.php')($router);
            $router->dispatch($request)->send();
        } catch (Throwable $e) {
            $this->handleException($e, $request)->send();
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function get(string $class): object
    {
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }
        $ref = new ReflectionClass($class);
        $args = [];
        foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = self::get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \LogicException("Impossibile risolvere {$class}::\${$param->getName()}");
            }
        }
        return self::$instances[$class] = $ref->newInstanceArgs($args);
    }

    /** Le POST devono arrivare da una pagina di Matriosga (protezione CSRF minima). */
    private function assertSameOrigin(Request $request): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($request->method() === 'POST' && $origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
            throw new HttpException(403, 'Richiesta rifiutata: origine non consentita.');
        }
    }

    private function handleException(Throwable $e, Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->getCode() : 500;
        if ($status >= 500) {
            self::get(Logger::class)->error($e->getMessage(), [
                'page'      => $request->path(),
                'exception' => $e::class,
                'at'        => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        $message = $e->getMessage();

        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }
        $html = (new View())->render('errors/error', [
            'title'       => 'Errore',
            'currentPath' => $request->path(),
            'status'  => $status,
            'message' => $message,
            'trace'   => (bool) self::get(Settings::class)->get('app.debug') ? $e->getTraceAsString() : null,
        ]);
        return Response::html($html, $status);
    }
}
