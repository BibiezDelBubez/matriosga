<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Catalog;
use Closure;
use RuntimeException;

/**
 * Cache su file dei metadata (solo struttura, nessun dato delle tabelle), divisa in parti
 * caricate solo quando servono: su DB con migliaia di tabelle un file unico costerebbe
 * centinaia di MB di RAM a ogni richiesta.
 *
 *   storage/cache/<hash connessione>/
 *     core.json            server, statistiche, oggetti (+ PK, n. colonne), FK   → ogni pagina
 *     columns.json         indice compatto di tutte le colonne                   → ricerche
 *     tables/<xx>.json     colonne complete + indici, 256 gruppi di tabelle      → dettaglio tabella
 */
final class MetadataCache
{
    private const DIR = BASE_PATH . '/storage/cache/';

    /** Posizioni nell'indice compatto delle colonne (columns.json). */
    public const C_NAME = 0, C_TYPE = 1, C_LEN = 2, C_PREC = 3, C_SCALE = 4, C_NULL = 5;

    /** Posizioni delle FK compatte in core.json. */
    private const FK_KEYS = ['name', 'from', 'to', 'from_cols', 'to_cols', 'on_delete', 'on_update', 'disabled', 'trusted'];

    private ?Catalog $catalog = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly MetadataService $metadata,
        private readonly Logger $logger,
    ) {
    }

    /** Catalog corrente: da cache se presente, altrimenti letto dal database. */
    public function catalog(): Catalog
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        if (!$this->settings->isConfigured()) {
            throw new HttpException(412, 'Connessione non configurata: aprire Impostazioni.');
        }
        $core = $this->read('core.json');
        if ($core === null || ($core['version'] ?? 0) !== MetadataService::SNAPSHOT_VERSION) {
            return $this->refresh(); // assente, vecchio formato o corrotto
        }
        return $this->catalog = $this->makeCatalog($core);
    }

    /** Rilegge la struttura dal database e riscrive la cache. */
    public function refresh(): Catalog
    {
        // Solo qui lo snapshot completo sta in memoria (su SGA: ~130.000 colonne).
        ini_set('memory_limit', '1536M');
        set_time_limit(600);
        return $this->store($this->metadata->snapshot());
    }

    /**
     * Scrive uno snapshot (formato MetadataService::snapshot) nella cache e lo attiva.
     * @param array<string, mixed> $snap
     */
    public function store(array $snap): Catalog
    {
        $final = $this->dir();
        $tmp = rtrim($final, '/') . '.tmp/';
        self::removeDir($tmp);
        if (!mkdir($tmp . 'tables', 0777, true)) {
            throw new RuntimeException('Impossibile creare la cartella di cache in storage/cache.');
        }

        $objects = [];
        $columnIndex = [];
        $shards = [];
        foreach ($snap['objects'] as $full => $o) {
            $cols = $snap['columns'][$full] ?? [];
            $idx = $snap['indexes'][$full] ?? [];
            $pk = array_values(array_filter($idx, static fn (array $i) => $i['pk']))[0] ?? null;
            $objects[$full] = $o + ['pk' => $pk['columns'] ?? [], 'ncols' => count($cols)];
            $columnIndex[$full] = array_map(static fn (array $c) => [$c['name'], $c['type'], $c['max_length'], $c['precision'], $c['scale'], $c['nullable']], $cols);
            $shards[self::shardFile($full)][$full] = ['columns' => $cols, 'indexes' => $idx];
        }
        foreach ($shards as $file => $tables) {
            self::writeJson($tmp . 'tables/' . $file, $tables);
        }
        $core = [
            'version'   => $snap['version'],
            'cached_at' => $snap['cached_at'],
            'seconds'   => $snap['seconds'],
            'server'    => $snap['server'],
            'stats'     => $snap['stats'],
            'objects'   => $objects,
            'fks'       => array_map(static fn (array $fk) => array_map(static fn ($k) => $fk[$k], self::FK_KEYS), $snap['fks']),
        ];
        self::writeJson($tmp . 'columns.json', $columnIndex);
        self::writeJson($tmp . 'core.json', $core); // per ultimo: la sua presenza indica cache completa

        self::removeDir($final);
        if (!rename(rtrim($tmp, '/'), rtrim($final, '/'))) {
            throw new RuntimeException('Impossibile attivare la nuova cache metadata.');
        }
        $this->logger->info('Metadata aggiornati', [
            'tables'  => count($snap['objects']),
            'fks'     => count($snap['fks']),
            'seconds' => $snap['seconds'],
        ]);
        return $this->catalog = $this->makeCatalog($core);
    }

    /**
     * Dati calcolati dai metadata (es. relazioni candidate): letti da derived-<nome>.json o
     * calcolati con $build e salvati. Si rigenerano automaticamente con "Aggiorna metadata".
     * @param Closure(): array $build
     * @return array<mixed>
     */
    public function derived(string $name, Closure $build): array
    {
        $file = 'derived-' . preg_replace('/[^a-z0-9_-]/', '', $name) . '.json';
        $data = $this->read($file);
        if ($data === null) {
            $data = $build();
            self::writeJson($this->dir() . $file, $data);
        }
        return $data;
    }

    /** Stato leggero (senza leggere i file) per la topbar. @return array{cached_at: string}|null */
    public function status(): ?array
    {
        $file = $this->dir() . 'core.json';
        if (!$this->settings->isConfigured() || !is_file($file)) {
            return null;
        }
        return ['cached_at' => date('d/m/Y H:i', (int) filemtime($file))];
    }

    /** @param array<string, mixed> $core */
    private function makeCatalog(array $core): Catalog
    {
        $core['fks'] = array_map(static fn (array $row) => array_combine(self::FK_KEYS, $row), $core['fks']);
        $loader = function (string $part, string $full = ''): array {
            $data = $part === 'table' ? ($this->read('tables/' . self::shardFile($full))[$full] ?? null) : $this->read('columns.json');
            if ($data === null) {
                throw new RuntimeException('Cache metadata incompleta: premere "Aggiorna metadata".');
            }
            return $data;
        };
        return new Catalog($core, $loader, (string) $this->settings->get('connection.schema', 'dbo'));
    }

    /** @return array<string, mixed>|null */
    private function read(string $relative): ?array
    {
        $file = $this->dir() . $relative;
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private function dir(): string
    {
        return self::DIR . md5($this->settings->connectionId()) . '/';
    }

    /** Dettaglio tabelle raggruppato in 256 file (8.000+ file singoli rallentano molto la scrittura su Windows). */
    private static function shardFile(string $full): string
    {
        return substr(md5(mb_strtolower($full)), 0, 2) . '.json';
    }

    /** @param array<mixed> $data */
    private static function writeJson(string $file, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || file_put_contents($file, $json) === false) {
            throw new RuntimeException('Impossibile scrivere la cache metadata in storage/cache.');
        }
    }

    private static function removeDir(string $dir): void
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
