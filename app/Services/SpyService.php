<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Settings;
use App\Helpers\Sql;
use App\Models\Catalog;
use App\Models\Column;
use RuntimeException;

/**
 * Spia modifiche: "cosa cambia nel database quando faccio questa operazione nel gestionale?".
 * Solo meccanismi standard di SQL Server, nessuna dipendenza da un gestionale specifico:
 *
 *  1. conteggio righe di TUTTE le tabelle prima/dopo (sys.partitions): righe aggiunte/tolte, istantaneo
 *  2. colonne rowversion/timestamp: ogni riga inserita o modificata riceve un valore > @@DBTS di partenza
 *  3. colonne "quando/chi" con nomi configurabili (config/app.php → spy): righe inserite/modificate
 *     nell'intervallo, facoltativamente solo di un utente
 *
 * Lo stato sta in storage/spy.json (una spia alla volta per installazione, legata alla connessione).
 */
final class SpyService
{
    private const FILE = BASE_PATH . '/storage/spy.json';
    private const BATCH_SECONDS = 6;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly Settings $settings,
        private readonly TableDataService $data,
    ) {
    }

    /** @return array<string, mixed>|null stato corrente (senza i conteggi, pesanti) */
    public function state(): ?array
    {
        $s = $this->load();
        if ($s === null) {
            return null;
        }
        unset($s['counts'], $s['counts_after']);
        return $s;
    }

    /** @return array<string, mixed> */
    public function start(string $user): array
    {
        $this->save([
            'connection' => $this->settings->connectionId(),
            'user'       => mb_substr(trim($user), 0, 100),
            'started_at' => $this->serverTime(),
            'dbts_from'  => $this->dbts(),
            'stopped_at' => null,
            'dbts_to'    => null,
            'counts'     => $this->counts(),
        ]);
        return (array) $this->state();
    }

    /**
     * Ferma la spia: righe aggiunte/tolte per tabella + tabelle da controllare riga per riga.
     * @return array{state: array, changes: list<array>, scan: list<string>}
     */
    public function stop(Catalog $catalog): array
    {
        $s = $this->load() ?? throw HttpException::badRequest('Nessuna spia avviata.');
        if ($s['stopped_at'] === null) {
            $s['stopped_at'] = $this->serverTime();
            $s['dbts_to'] = $this->dbts();
            $s['counts_after'] = $this->counts();
            $this->save($s);
        }
        $changes = [];
        foreach ($s['counts_after'] as $full => $after) {
            $delta = $after - ($s['counts'][$full] ?? 0);
            if ($delta !== 0) {
                $changes[] = ['table' => $full, 'delta' => $delta, 'before' => $s['counts'][$full] ?? 0, 'after' => $after];
            }
        }
        usort($changes, static fn (array $a, array $b) => abs($b['delta']) <=> abs($a['delta']));

        // Da controllare riga per riga: tabelle tracciabili che avevano righe (le vuote le coprono i conteggi).
        $scan = [];
        foreach ($catalog->objects() as $full => $o) {
            if ($o['type'] === 'U' && ($s['counts'][$full] ?? 0) > 0 && $this->tracking($catalog, $full) !== null) {
                $scan[] = $full;
            }
        }
        unset($s['counts'], $s['counts_after']);
        return ['state' => $s, 'changes' => $changes, 'scan' => $scan];
    }

    /**
     * Righe nuove/modificate nell'intervallo, per tabella (a lotti di BATCH_SECONDS).
     * @param list<string> $names
     * @return array{done: list<string>, hits: list<array>, errors: list<array{0: string, 1: string}>}
     */
    public function scan(Catalog $catalog, array $names): array
    {
        set_time_limit(0);
        $s = $this->stopped();
        $start = microtime(true);
        $done = $hits = $errors = [];
        foreach ($names as $name) {
            if ($done && microtime(true) - $start > self::BATCH_SECONDS) {
                break;
            }
            $full = $catalog->resolve($name);
            $track = $full !== null ? $this->tracking($catalog, $full) : null;
            if ($full === null || $track === null) {
                continue;
            }
            $done[] = $full;
            $preds = $this->predicates($track, $s['user'] !== '');
            $sums = implode(', ', array_map(static fn (string $k, string $p) => "SUM(CASE WHEN {$p} THEN 1 ELSE 0 END) AS [{$k}]", array_keys($preds), $preds));
            [$with, $params] = $this->window($s);
            try {
                $row = $this->db->selectOne("{$with} SELECT {$sums} FROM {$catalog->quoted($full)} AS t CROSS JOIN p WHERE "
                    . implode(' OR ', array_map(static fn (string $p) => "({$p})", $preds)), $params) ?? [];
            } catch (RuntimeException $e) {
                $errors[] = [$full, $e->getMessage()];
                continue;
            }
            $counts = array_map('intval', $row);
            if (array_sum($counts) > 0) {
                $hits[] = [
                    'table'    => $full,
                    'inserted' => $counts['inserted'] ?? null,
                    'updated'  => $counts['updated'] ?? null,
                    'changed'  => $counts['changed'] ?? null, // solo rowversion: nuove o modificate
                    'by_user'  => $s['user'] !== '' && ($track['ins_user'] !== null || $track['upd_user'] !== null),
                    'method'   => isset($preds['changed']) ? 'rowversion' : 'date',
                ];
            }
        }
        return ['done' => $done, 'hits' => $hits, 'errors' => $errors];
    }

    /** Righe nuove o modificate nell'intervallo (finestra "vedi righe"). @return array<string, mixed> */
    public function rows(Catalog $catalog, string $tableName): array
    {
        $s = $this->stopped();
        $table = $catalog->require($tableName);
        $track = $this->tracking($catalog, $table->fullName)
            ?? throw HttpException::badRequest('Questa tabella non ha colonne data/ora né rowversion: si vede solo quante righe sono state aggiunte o tolte.');
        [$with, $params] = $this->window($s);
        $where = implode(' OR ', array_map(static fn (string $p) => "({$p})", $this->predicates($track, $s['user'] !== '')));
        return $this->data->rows($table, $where, $params, $with);
    }

    public function reset(): void
    {
        if (is_file(self::FILE)) {
            unlink(self::FILE);
        }
    }

    /**
     * Come si può tracciare una tabella: colonne data inserimento/modifica e utente (nomi da config)
     * e colonna rowversion. null = solo conteggio righe.
     * @return array{ins: ?string, upd: ?string, ins_user: ?string, upd_user: ?string, rv: ?string}|null
     */
    public function tracking(Catalog $catalog, string $full): ?array
    {
        $wanted = [
            'ins'      => ['insert_columns', 'date'],
            'upd'      => ['update_columns', 'date'],
            'ins_user' => ['insert_user_columns', 'string'],
            'upd_user' => ['update_user_columns', 'string'],
        ];
        $found = ['ins' => null, 'upd' => null, 'ins_user' => null, 'upd_user' => null, 'rv' => null];
        $cols = [];
        foreach ($catalog->columnIndex()[$full] ?? [] as $c) {
            $cols[mb_strtolower($c[MetadataCache::C_NAME])] = $c;
            if ($c[MetadataCache::C_TYPE] === 'timestamp') { // rowversion
                $found['rv'] = $c[MetadataCache::C_NAME];
            }
        }
        foreach ($wanted as $key => [$setting, $category]) {
            foreach ((array) $this->settings->get("spy.{$setting}", []) as $name) {
                $c = $cols[mb_strtolower((string) $name)] ?? null;
                if ($c !== null && Column::categoryOf($c[MetadataCache::C_TYPE]) === $category) {
                    $found[$key] = $c[MetadataCache::C_NAME];
                    break;
                }
            }
        }
        return $found['ins'] === null && $found['upd'] === null && $found['rv'] === null ? null : $found;
    }

    /**
     * Condizioni per contare: con colonne data → 'inserted' e 'updated' (anche per utente);
     * solo rowversion → 'changed' (nuove o modificate, senza utente).
     * @return array<string, string>
     */
    private function predicates(array $t, bool $byUser): array
    {
        $q = static fn (string $col) => 't.' . Sql::quoteIdent($col);
        $in = static fn (string $col) => "{$q($col)} >= p.mt_from AND {$q($col)} <= p.mt_to";
        $user = static fn (?string $col) => $byUser && $col !== null ? " AND {$q($col)} = p.mt_user" : '';
        if ($t['ins'] === null && $t['upd'] === null) {
            return ['changed' => "{$q($t['rv'])} > p.mt_rv_from AND {$q($t['rv'])} <= p.mt_rv_to"];
        }
        $out = [];
        if ($t['ins'] !== null) {
            $out['inserted'] = $in($t['ins']) . $user($t['ins_user']);
        }
        if ($t['upd'] !== null) {
            $notNew = $t['ins'] !== null ? " AND ({$q($t['ins'])} IS NULL OR {$q($t['ins'])} < p.mt_from)" : '';
            $out['updated'] = $in($t['upd']) . $notNew . $user($t['upd_user']);
        }
        return $out;
    }

    /** CTE con intervallo di tempo (ora del server), intervallo rowversion e utente. @return array{0: string, 1: array<string, string>} */
    private function window(array $s): array
    {
        return [
            'WITH p AS (SELECT CAST(:f AS datetime2) AS mt_from, CAST(:t AS datetime2) AS mt_to, CAST(:u AS nvarchar(100)) AS mt_user,'
            . ' CONVERT(binary(8), :rf, 1) AS mt_rv_from, CONVERT(binary(8), :rt, 1) AS mt_rv_to)',
            ['f' => $s['started_at'], 't' => $s['stopped_at'], 'u' => $s['user'], 'rf' => $s['dbts_from'], 'rt' => $s['dbts_to']],
        ];
    }

    /** @return array<string, int> "schema.tabella" => righe (tutte le tabelle utente) */
    private function counts(): array
    {
        $out = [];
        foreach ($this->db->select(
            "SELECT s.name + '.' + o.name AS name, SUM(p.rows) AS n
             FROM sys.partitions p JOIN sys.objects o ON o.object_id = p.object_id JOIN sys.schemas s ON s.schema_id = o.schema_id
             WHERE o.type = 'U' AND o.is_ms_shipped = 0 AND p.index_id IN (0, 1)
             GROUP BY s.name, o.name"
        ) as $r) {
            $out[$r['name']] = (int) $r['n'];
        }
        return $out;
    }

    private function serverTime(): string
    {
        return (string) $this->db->selectOne('SELECT CONVERT(varchar(23), SYSDATETIME(), 121) AS t')['t'];
    }

    /** Ultimo valore rowversion usato nel database, come stringa esadecimale 0x… */
    private function dbts(): string
    {
        return (string) $this->db->selectOne('SELECT CONVERT(varchar(20), @@DBTS, 1) AS v')['v'];
    }

    /** @return array<string, mixed> */
    private function stopped(): array
    {
        $s = $this->load();
        if ($s === null || $s['stopped_at'] === null) {
            throw HttpException::badRequest('Prima avvia e ferma la spia.');
        }
        return $s;
    }

    /** @return array<string, mixed>|null */
    private function load(): ?array
    {
        $s = is_file(self::FILE) ? json_decode((string) file_get_contents(self::FILE), true) : null;
        return is_array($s) && ($s['connection'] ?? '') === $this->settings->connectionId() ? $s : null;
    }

    private function save(array $s): void
    {
        if (file_put_contents(self::FILE, (string) json_encode($s), LOCK_EX) === false) {
            throw new RuntimeException('Impossibile scrivere storage/spy.json.');
        }
    }
}
