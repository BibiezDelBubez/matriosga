<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderer PHP: la vista viene resa e poi inserita nel layout ($content).
 * Nelle viste usare $this->partial('partials/nome', [...]) per i componenti riusabili.
 */
final class View
{
    private const DIR = BASE_PATH . '/app/Views/';

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout/main'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        $file = self::DIR . $template . '.php';
        if (!is_file($file)) {
            throw new \LogicException("Vista non trovata: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
