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
 * Ricerca di un valore in tutto il database, in modo controllato:
 *  1. plan(): dalle sole colonne in cache sceglie tabelle/colonne compatibili (tipo, lunghezza)
 *  2. run():  per ogni tabella UNA scansione che conta le occorrenze di tutte le colonne candidate;
 *             si ferma dopo BATCH_SECONDS e restituisce le tabelle fatte (il client rimanda le altre)
 *  3. rows(): righe trovate per una tabella/colonna (TOP N) + SQL/Power Query da copiare
 *
 * I valori passano come parametri in una CTE "p" (così si usano una sola volta anche se servono
 * per molte colonne); nomi di tabelle e colonne arrivano sempre dal Catalog.
 */
final class ValueSearchService
{
    private const BATCH_SECONDS = 6;
    private const MAX_VALUE_LENGTH = 4000;
    private const ANSI = ['char', 'varchar', 'text'];
    private const UNICODE = ['nchar', 'nvarchar', 'sysname', 'ntext'];
    private const LIKE_ONLY = ['text', 'ntext'];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly Settings $settings,
        private readonly TableDataService $data,
        private readonly QueryTextService $queryText,
    ) {
    }

    /**
     * Criteri normalizzati e validati.
     * @return array{value: string, mode: string, numbers: bool, views: bool, guid: bool, numeric: bool, ansi: bool, ci: bool}
     */
    public function criteria(Catalog $catalog, string $value, string $mode, bool $numbers, bool $views): array
    {
        if ($value === '') {
            throw HttpException::badRequest('Inserire un valore da cercare.');
        }
        if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            throw HttpException::badRequest('Valore troppo lungo (massimo ' . self::MAX_VALUE_LENGTH . ' caratteri).');
        }
        $collation = (string) ($catalog->server()['collation'] ?? '');
        return [
            'value'   => $value,
            'mode'    => $mode,
            'numbers' => $numbers,
            'views'   => $views,
            'guid'    => (bool) preg_match('/^\{?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\}?$/i', $value),
            'numeric' => $numbers && $mode === 'equals' && is_numeric($value) && preg_match('/^-?\d{1,28}(\.\d{1,10})?$/', $value),
            // il valore è rappresentabile in varchar? (altrimenti le colonne non-Unicode non possono contenerlo)
            'ansi'    => mb_convert_encoding(mb_convert_encoding($value, 'Windows-1252', 'UTF-8'), 'UTF-8', 'Windows-1252') === $value,
            // collation case-sensitive del database: forziamo il confronto case-insensitive
            'ci'      => !str_contains(strtoupper($collation), '_CS'),
        ];
    }

    /**
     * @return array{tables: list<array{0: string, 1: ?int, 2: int}>, skipped: list<array{0: string, 1: ?int, 2: int}>, columns: int, maxRows: int}
     */
    /** @param array<string, true> $hidden tabelle da non interrogare (copie, vedi TableFilterService) */
    public function plan(Catalog $catalog, array $crit, bool $force, array $hidden = []): array
    {
        $maxRows = (int) $this->settings->get('limits.search_max_table_rows');
        $objects = $catalog->objects();
        $tables = $skipped = [];
        $columns = 0;
        foreach ($catalog->columnIndex() as $full => $cols) {
            if (($objects[$full]['type'] === 'V' && !$crit['views']) || isset($hidden[$full])) {
                continue;
            }
            $n = 0;
            foreach ($cols as $c) {
                if ($this->predicate('x', $c[MetadataCache::C_TYPE], $c[MetadataCache::C_LEN], $crit) !== null) {
                    $n++;
                }
            }
            if ($n === 0) {
                continue;
            }
            $rows = $objects[$full]['rows'];
            if ($rows === 0) {
                continue; // tabella vuota: niente da cercare
            }
            $entry = [$full, $rows, $n];
            if (!$force && $maxRows > 0 && $rows !== null && $rows > $maxRows) {
                $skipped[] = $entry;
            } else {
                $tables[] = $entry;
                $columns += $n;
            }
        }
        // Prima le tabelle piccole: i primi risultati arrivano subito.
        usort($tables, static fn (array $a, array $b) => ($a[1] ?? 0) <=> ($b[1] ?? 0));
        usort($skipped, static fn (array $a, array $b) => ($a[1] ?? 0) <=> ($b[1] ?? 0));
        return ['tables' => $tables, 'skipped' => $skipped, 'columns' => $columns, 'maxRows' => $maxRows];
    }

    /**
     * @param list<string> $names tabelle da interrogare (validate contro il Catalog)
     * @return array{done: list<string>, hits: list<array<string, mixed>>, errors: list<array{0: string, 1: string}>}
     */
    public function run(Catalog $catalog, array $crit, array $names): array
    {
        set_time_limit(0);
        $start = microtime(true);
        $index = $catalog->columnIndex();
        [$with, $params] = $this->cte($crit);
        $done = $hits = $errors = [];

        foreach ($names as $name) {
            if ($done && microtime(true) - $start > self::BATCH_SECONDS) {
                break; // il client rimanderà le tabelle rimaste
            }
            $full = $catalog->resolve($name);
            if ($full === null) {
                continue;
            }
            $done[] = $full;
            $cols = [];
            foreach ($index[$full] ?? [] as $c) {
                $pred = $this->predicate('t.' . Sql::quoteIdent($c[MetadataCache::C_NAME]), $c[MetadataCache::C_TYPE], $c[MetadataCache::C_LEN], $crit);
                if ($pred !== null) {
                    $cols[] = [$c, $pred];
                }
            }
            if (!$cols) {
                continue;
            }
            $sums = [];
            foreach ($cols as $i => [, $pred]) {
                $sums[] = "SUM(CASE WHEN {$pred} THEN 1 ELSE 0 END) AS [h{$i}]";
            }
            $sql = $with . ' SELECT ' . implode(', ', $sums)
                . ' FROM ' . $catalog->quoted($full) . ' AS t CROSS JOIN p WHERE '
                . implode(' OR ', array_map(static fn (array $x) => '(' . $x[1] . ')', $cols));
            try {
                $row = $this->db->select($sql, $params)[0] ?? [];
            } catch (RuntimeException $e) {
                $errors[] = [$full, $e->getMessage()];
                continue;
            }
            foreach ($cols as $i => [$c]) {
                $count = (int) ($row["h{$i}"] ?? 0);
                if ($count > 0) {
                    $hits[] = [
                        'table'  => $full,
                        'column' => $c[MetadataCache::C_NAME],
                        'type'   => Column::label($c[MetadataCache::C_TYPE], $c[MetadataCache::C_LEN], $c[MetadataCache::C_PREC], $c[MetadataCache::C_SCALE]),
                        'count'  => $count,
                        'rows'   => $catalog->objects()[$full]['rows'],
                    ];
                }
            }
        }
        return ['done' => $done, 'hits' => $hits, 'errors' => $errors];
    }

    /**
     * Righe di una tabella in cui la colonna corrisponde al valore + SQL e Power Query da copiare.
     * @return array<string, mixed>
     */
    public function rows(Catalog $catalog, array $crit, string $tableName, string $columnName): array
    {
        $table = $catalog->require($tableName);
        $column = $table->column($columnName) ?? throw HttpException::notFound("Colonna non trovata: {$columnName}");
        $pred = $this->predicate('t.' . $column->quoted(), $column->type, $column->maxLength, $crit);
        if ($pred === null) {
            throw HttpException::badRequest('La colonna non è compatibile con il valore cercato.');
        }
        [$with, $params] = $this->cte($crit);
        $data = $this->data->rows($table, $pred, $params, $with);

        $where = $this->predicate($column->quoted(), $column->type, $column->maxLength, $crit, true);
        $sql = $this->queryText->select($table, null, null, (string) $where);
        return $data + [
            'sql' => $sql,
            'pq'  => $this->queryText->powerQueryNative($sql),
        ];
    }

    /**
     * Condizione SQL per una colonna, o null se la colonna non può contenere il valore.
     * $display = true produce la versione leggibile con letterali (per "Copia SQL"), mai eseguita.
     */
    private function predicate(string $col, string $type, int $maxLength, array $crit, bool $display = false): ?string
    {
        $value = $crit['value'];
        $equals = $crit['mode'] === 'equals';
        $isAnsi = in_array($type, self::ANSI, true);

        if ($isAnsi || in_array($type, self::UNICODE, true)) {
            $len = Column::lengthOf($type, $maxLength);
            if (($isAnsi && !$crit['ansi']) || ($len !== null && $len !== -1 && $len < mb_strlen($value))) {
                return null;
            }
            $collate = $crit['ci'] ? '' : ' COLLATE Latin1_General_CI_AI';
            if ($equals && !in_array($type, self::LIKE_ONLY, true)) {
                $rhs = $display ? Sql::literal($value) : ($isAnsi ? 'p.mt_va' : 'p.mt_v');
                return "{$col}{$collate} = {$rhs}";
            }
            $rhs = $display ? Sql::literal(Sql::likePattern($value, $crit['mode'])) : ($isAnsi ? 'p.mt_la' : 'p.mt_l');
            return "{$col}{$collate} LIKE {$rhs}";
        }
        if ($type === 'uniqueidentifier' && $crit['guid']) {
            return $col . ' = ' . ($display ? "'" . trim($value, '{}') . "'" : 'p.mt_g');
        }
        if ($crit['numeric'] && in_array(Column::categoryOf($type), ['int', 'number'], true) && $type !== 'bit') {
            return $col . ' = ' . ($display ? $value : 'p.mt_n');
        }
        return null;
    }

    /** CTE con i parametri: ogni parametro compare una volta sola nel testo SQL. @return array{0: string, 1: array<string, string>} */
    private function cte(array $crit): array
    {
        $pattern = Sql::likePattern($crit['value'], $crit['mode']);
        $fields = [
            'CAST(:v AS nvarchar(4000)) AS mt_v',
            'CAST(:va AS varchar(8000)) AS mt_va',
            'CAST(:l AS nvarchar(4000)) AS mt_l',
            'CAST(:la AS varchar(8000)) AS mt_la',
        ];
        $params = ['v' => $crit['value'], 'va' => $crit['value'], 'l' => $pattern, 'la' => $pattern];
        if ($crit['guid']) {
            $fields[] = 'CAST(:g AS uniqueidentifier) AS mt_g';
            $params['g'] = trim($crit['value'], '{}');
        }
        if ($crit['numeric']) {
            $fields[] = 'CAST(:n AS decimal(38,10)) AS mt_n';
            $params['n'] = $crit['value'];
        }
        return ['WITH p AS (SELECT ' . implode(', ', $fields) . ')', $params];
    }
}
