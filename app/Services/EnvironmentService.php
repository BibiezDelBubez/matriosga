<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/**
 * Controlli sull'ambiente server (PHP, driver, Apache, permessi) con istruzioni per sistemare.
 * Serve soprattutto quando si installa Matriosga su una macchina nuova.
 *
 * Ogni controllo: [id, label, status ok|warn|error|info, value, fix (lista di passi), code (righe da copiare)]
 */
final class EnvironmentService
{
    public function __construct(
        private readonly MetadataCache $cache,
        private readonly Settings $settings,
    ) {
    }

    /** @return list<array{id: string, label: string, status: string, value: string, why: string, fix: list<string>, code: string}> */
    public function checks(): array
    {
        $ini = php_ini_loaded_file() ?: '(php.ini non trovato)';
        $extDir = (string) ini_get('extension_dir');
        $checks = [];

        $checks[] = $this->check('php', 'Versione PHP', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'error',
            PHP_VERSION . (PHP_ZTS ? ' (thread safe)' : ' (non thread safe)') . ', ' . (PHP_INT_SIZE * 8) . ' bit',
            'Serve PHP 8.1 o superiore (XAMPP 8.2 va bene).',
            ['Installare XAMPP con PHP 8.2 (versione Windows, 64 bit).']);

        $dll = sprintf('php_pdo_sqlsrv_%d%d_%s.dll', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_ZTS ? 'ts' : 'nts');
        $checks[] = $this->check('pdo_sqlsrv', 'Estensione PHP pdo_sqlsrv', extension_loaded('pdo_sqlsrv') ? 'ok' : 'error',
            extension_loaded('pdo_sqlsrv') ? 'caricata (versione ' . phpversion('pdo_sqlsrv') . ')' : 'NON caricata',
            'È il driver con cui PHP parla con SQL Server. Senza, Matriosga non può collegarsi.',
            [
                "Scaricare da un PC con Internet i «Microsoft Drivers for PHP for SQL Server» e prendere il file {$dll} (x64).",
                "Copiarlo in {$extDir}",
                "Aprire {$ini} e aggiungere la riga qui sotto (vicino alle altre extension=).",
                'Riavviare Apache dal pannello di XAMPP.',
            ],
            'extension=' . $dll);

        $odbc = $this->odbcDriver();
        $checks[] = $this->check('odbc', 'Microsoft ODBC Driver for SQL Server', $odbc !== null ? 'ok' : (PHP_OS_FAMILY === 'Windows' ? 'error' : 'info'),
            $odbc ?? 'non trovato',
            'pdo_sqlsrv si appoggia a questo driver di sistema (versione 17 o 18, 64 bit).',
            ['Installare «Microsoft ODBC Driver 18 for SQL Server» (x64) con il suo setup .msi, poi riavviare Apache.']);

        $opcache = extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
        $checks[] = $this->check('opcache', 'OPcache (velocità)', $opcache ? 'ok' : 'warn',
            $opcache ? 'attivo' : 'spento',
            'Tiene in memoria il codice PHP già compilato: le pagine con molte righe diventano 2-5 volte più veloci. Non cambia il funzionamento.',
            [
                "Aprire {$ini}",
                'Togliere il ; davanti a «zend_extension=opcache» (oppure aggiungere la riga).',
                'Nella sezione [opcache] impostare i valori qui sotto (togliendo il ; iniziale).',
                'Riavviare Apache.',
            ],
            "zend_extension=opcache\n\n[opcache]\nopcache.enable=1\nopcache.memory_consumption=128\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=10000\nopcache.validate_timestamps=1\nopcache.revalidate_freq=2");

        $memory = self::bytes((string) ini_get('memory_limit'));
        $checks[] = $this->check('memory', 'Memoria PHP (memory_limit)', $memory < 0 || $memory >= 512 * 1024 ** 2 ? 'ok' : ($memory >= 256 * 1024 ** 2 ? 'warn' : 'error'),
            (string) ini_get('memory_limit'),
            'Le pagine normali usano poca memoria; «Aggiorna metadata» la alza da sola fino a 1,5 GB. Sotto 256M alcune ricerche possono fallire.',
            ["In {$ini} impostare la riga qui sotto e riavviare Apache."],
            'memory_limit=512M');

        foreach (['openssl' => 'cifratura della password salvata', 'mbstring' => 'testi con accenti', 'json' => 'cache e API'] as $ext => $why) {
            $checks[] = $this->check($ext, "Estensione PHP {$ext}", extension_loaded($ext) ? 'ok' : 'error',
                extension_loaded($ext) ? 'caricata' : 'NON caricata', "Necessaria per: {$why}.",
                ["In {$ini} togliere il ; davanti a «extension={$ext}» e riavviare Apache."]);
        }

        $modules = function_exists('apache_get_modules') ? apache_get_modules() : null;
        $checks[] = $this->check('deflate', 'Compressione HTTP (Apache mod_deflate)',
            $modules === null ? 'info' : (in_array('mod_deflate', $modules, true) ? 'ok' : 'warn'),
            $modules === null ? 'non verificabile' : (in_array('mod_deflate', $modules, true) ? 'attiva' : 'spenta'),
            'Comprime pagine e dati inviati al browser (elenco tabelle: da ~800 KB a ~80 KB). Utile soprattutto se usi Matriosga da un altro PC.',
            [
                'Aprire xampp\\apache\\conf\\httpd.conf',
                'Togliere il # davanti alla riga qui sotto.',
                'Riavviare Apache. Matriosga la usa da solo (regole già presenti nel suo .htaccess).',
            ],
            'LoadModule deflate_module modules/mod_deflate.so');

        $checks[] = $this->check('rewrite', 'URL rewriting (Apache mod_rewrite)',
            $modules === null || in_array('mod_rewrite', $modules, true) ? 'ok' : 'error',
            $modules === null ? 'funzionante (questa pagina si è aperta)' : (in_array('mod_rewrite', $modules, true) ? 'attivo' : 'spento'),
            'Serve per gli indirizzi delle pagine e per nascondere le cartelle interne (config, storage).',
            ['In httpd.conf togliere il # davanti a «LoadModule rewrite_module modules/mod_rewrite.so» e verificare «AllowOverride All» per htdocs.']);

        $storage = BASE_PATH . '/storage';
        $writable = is_writable($storage) && is_writable($storage . '/cache') && is_writable($storage . '/logs');
        $checks[] = $this->check('storage', 'Cartella storage scrivibile', $writable ? 'ok' : 'error',
            $writable ? 'sì' : 'NO', 'Qui Matriosga salva impostazioni, cache della struttura e log.',
            ["Dare all'utente con cui gira Apache i permessi di scrittura su {$storage}"]);

        $keyFile = $this->settings->keyFile();
        $inside = str_starts_with(str_replace('\\', '/', strtolower((string) (realpath($keyFile) ?: $keyFile))), str_replace('\\', '/', strtolower(BASE_PATH)));
        $checks[] = $this->check('secret', 'Password del database', match (true) {
            !$this->settings->passwordReadable() => 'error',
            $inside && $this->settings->hasPassword() => 'warn',
            default => 'ok',
        }, match (true) {
            !$this->settings->hasPassword() => 'nessuna password salvata',
            !$this->settings->passwordReadable() => 'non decifrabile: chiave non trovata in ' . $keyFile,
            default => 'cifrata; chiave in ' . $keyFile,
        },
            'La password è cifrata in storage/secrets.json con la chiave app.key. Se la chiave sta nella stessa cartella, chi copia tutta la cartella può decifrarla: meglio tenerla fuori dal progetto.',
            $this->settings->passwordReadable() ? [
                'Creare una cartella fuori dal progetto e fuori da htdocs, es. C:\\MatriosgaChiave',
                "Spostarci il file {$keyFile}",
                'In config/app.php, sezione security, impostare key_file con il nuovo percorso (riga qui sotto).',
                'Ricaricare questa pagina: la voce diventa verde.',
            ] : [
                'La chiave app.key è stata persa o spostata: rimetterla nel percorso indicato,',
                'oppure reinserire la password in Impostazioni → Connessione e salvare.',
            ],
            "'key_file' => 'C:\\\\MatriosgaChiave\\\\app.key',");

        $status = $this->cache->status();
        $checks[] = $this->check('metadata', 'Cache struttura database', $status ? 'ok' : 'info',
            $status ? 'aggiornata al ' . $status['cached_at'] : 'non ancora creata',
            'Copia locale di tabelle, colonne, chiavi. Si crea da sola al primo accesso (circa 1 minuto su SGA).',
            ['Dopo aver configurato la connessione aprire la Dashboard, oppure premere «Aggiorna metadata».']);

        return $checks;
    }

    /** Percorsi utili per sapere quali file modificare. @return array<string, string> */
    public function paths(): array
    {
        return [
            'php.ini'        => php_ini_loaded_file() ?: '—',
            'Estensioni PHP' => (string) ini_get('extension_dir'),
            'Server web'     => (string) ($_SERVER['SERVER_SOFTWARE'] ?? '—'),
            'Matriosga'      => BASE_PATH,
        ];
    }

    /** @return array{ok: int, warn: int, error: int} */
    public function summary(): array
    {
        $count = ['ok' => 0, 'warn' => 0, 'error' => 0];
        foreach ($this->checks() as $c) {
            if (isset($count[$c['status']])) {
                $count[$c['status']]++;
            }
        }
        return $count;
    }

    /** @param list<string> $fix */
    private function check(string $id, string $label, string $status, string $value, string $why, array $fix, string $code = ''): array
    {
        return compact('id', 'label', 'status', 'value', 'why', 'fix', 'code');
    }

    private function odbcDriver(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }
        $dir = (getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\';
        foreach (['msodbcsql18.dll' => 'ODBC Driver 18', 'msodbcsql17.dll' => 'ODBC Driver 17'] as $file => $name) {
            if (is_file($dir . $file)) {
                return $name . ' installato';
            }
        }
        return null;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $n = (int) $value;
        return match (strtoupper(substr($value, -1))) {
            'G' => $n * 1024 ** 3,
            'M' => $n * 1024 ** 2,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
