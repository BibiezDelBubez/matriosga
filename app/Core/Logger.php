<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Log locale giornaliero in storage/logs/app-YYYY-MM-DD.log (una riga JSON per evento), conservato 30 giorni.
 * Le chiavi che somigliano a password vengono sempre oscurate.
 */
final class Logger
{
    private const DIR = BASE_PATH . '/storage/logs/';
    private const KEEP_DAYS = 30;

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        array_walk_recursive($context, static function (mixed &$value, string|int $key): void {
            if (is_string($key) && preg_match('/pass|pwd|secret/i', $key)) {
                $value = '***';
            }
        });
        $line = json_encode([
            'time'    => date('Y-m-d H:i:s'),
            'level'   => $level,
            'message' => $message,
        ] + $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $file = self::DIR . 'app-' . date('Y-m-d') . '.log';
        $isNewDay = !is_file($file);
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($isNewDay) {
            $this->purgeOld();
        }
    }

    /** Una volta al giorno: cancella i log più vecchi di KEEP_DAYS. */
    private function purgeOld(): void
    {
        $limit = time() - self::KEEP_DAYS * 86400;
        foreach (glob(self::DIR . 'app-*.log') ?: [] as $old) {
            if (filemtime($old) < $limit) {
                @unlink($old);
            }
        }
    }
}
