<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Helpers\Sql;
use App\Models\Catalog;
use App\Models\Column;
use App\Models\Table;

/**
 * Costruttore di query: dal browser arriva solo una DESCRIZIONE (tabelle, collegamenti, colonne, filtri),
 * mai testo SQL. Qui ogni nome viene validato sul Catalog e l'SQL è costruito da zero.
 *
 * spec = {
 *   tables:  [{alias: "t0", name: "dbo.A"}, {alias: "t1", name: "dbo.B",
 *             via: {parent: "t0", cols_parent: [...], cols_child: [...], join: "LEFT"|"INNER", kind: "fk"|"candidate"}}],
 *   columns: [{t: "t0", c: "NUMERO"}, ...],
 *   filters: [{t: "t1", c: "SOCIETA", op: "=", v: "1"}, ...]
 * }
 */
final class QueryBuilderService
{
    public const OPERATORS = [
        '='       => 'uguale a',
        '<>'      => 'diverso da',
        '>'       => 'maggiore di',
        '>='      => 'maggiore o uguale a',
        '<'       => 'minore di',
        '<='      => 'minore o uguale a',
        'contains'=> 'contiene',
        'starts'  => 'inizia con',
        'null'    => 'è vuoto (NULL)',
        'notnull' => 'non è vuoto',
    ];
    private const MAX_TABLES = 15;
    private const MAX_FILTERS = 20;
    private const PREVIEW_ROWS = 50;

    public function __construct(
        private readonly PathFinderService $paths,
        private readonly TableDataService $data,
    ) {
    }

    /**
     * Percorsi per collegare una tabella nuova al gruppo, già pronti per il browser.
     * @param list<string> $existing
     * @return list<array{nodes: list<string>, candidates: int, steps: list<array>}>
     */
    public function connect(Catalog $catalog, string $new, array $existing, array $opt): array
    {
        $new = $catalog->require($new)->fullName;
        $existing = array_values(array_unique(array_map(static fn (string $t) => $catalog->require($t)->fullName, $existing)));
        if (!$existing || in_array($new, $existing, true)) {
            return [];
        }
        return array_map(static fn (array $p) => [
            'nodes'      => $p['nodes'],
            'candidates' => $p['candidates'],
            'weak'       => $p['weak'],
            'steps'      => array_map(static fn (array $s) => [
                'parent'      => $s['a'],
                'child'       => $s['b'],
                'cols_parent' => $s['cols_a'],
                'cols_child'  => $s['cols_b'],
                'kind'        => $s['rel']['kind'],
                'label'       => $s['rel']['kind'] === 'candidate' ? 'candidata ' . $s['rel']['score'] . '%' : $s['rel']['name'],
            ], $p['steps']),
        ], $this->paths->connect($catalog, $new, $existing, $opt));
    }

    /**
     * SQL da mostrare/copiare (valori come letterali) oppure da eseguire (valori come parametri).
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function build(Catalog $catalog, array $spec, bool $forDisplay, ?int $top = null): array
    {
        $tables = $this->tables($catalog, $spec['tables'] ?? []);
        $select = $this->columns($tables, $spec['columns'] ?? []);
        [$where, $params] = $this->filters($tables, $spec['filters'] ?? [], $forDisplay);

        $from = '';
        foreach ($tables as $alias => [$table, $via]) {
            if ($via === null) {
                $from = "FROM {$table->quoted()} AS {$alias}";
                continue;
            }
            $on = [];
            foreach ($via['cols_parent'] as $i => $pc) {
                $on[] = "{$alias}." . Sql::quoteIdent($via['cols_child'][$i]) . " = {$via['parent']}." . Sql::quoteIdent($pc);
            }
            $note = QueryTextService::kindComment($via['kind']);
            $from .= "\n{$via['join']} JOIN {$table->quoted()} AS {$alias}{$note}\n    ON " . implode("\n   AND ", $on);
        }
        $sql = 'SELECT' . ($top !== null ? " TOP ({$top})" : '') . "\n    " . implode(",\n    ", $select) . "\n" . $from
            . ($where ? "\nWHERE " . implode("\n  AND ", $where) : '');
        return ['sql' => $sql, 'params' => $params];
    }

    /** Prime righe del risultato. @return array<string, mixed> */
    public function preview(Catalog $catalog, array $spec): array
    {
        $q = $this->build($catalog, $spec, false, self::PREVIEW_ROWS);
        return $this->data->run($q['sql'], $q['params'], self::PREVIEW_ROWS);
    }

    /**
     * Valida tabelle e collegamenti. @return array<string, array{0: Table, 1: ?array}> alias => [tabella, via]
     */
    private function tables(Catalog $catalog, array $specTables): array
    {
        if (!$specTables) {
            throw HttpException::badRequest('Aggiungi almeno una tabella.');
        }
        if (count($specTables) > self::MAX_TABLES) {
            throw HttpException::badRequest('Troppe tabelle (massimo ' . self::MAX_TABLES . ').');
        }
        $out = [];
        foreach (array_values($specTables) as $i => $t) {
            $alias = 't' . $i; // alias sempre rigenerati dal server: t0, t1, ...
            if (($t['alias'] ?? '') !== $alias) {
                throw HttpException::badRequest('Descrizione della query non valida (alias).');
            }
            $table = $catalog->require((string) ($t['name'] ?? ''));
            $via = null;
            if ($i > 0) {
                $v = $t['via'] ?? null;
                $parent = is_array($v) ? ($out[$v['parent'] ?? ''] ?? null) : null;
                $colsP = (array) ($v['cols_parent'] ?? []);
                $colsC = (array) ($v['cols_child'] ?? []);
                if ($parent === null || !$colsP || count($colsP) !== count($colsC)) {
                    throw HttpException::badRequest("Collegamento non valido per {$table->fullName}.");
                }
                $via = [
                    'parent'      => $v['parent'],
                    'cols_parent' => array_map(fn ($c) => $this->column($parent[0], (string) $c)->name, $colsP),
                    'cols_child'  => array_map(fn ($c) => $this->column($table, (string) $c)->name, $colsC),
                    'join'        => ($v['join'] ?? 'LEFT') === 'INNER' ? 'INNER' : 'LEFT',
                    'kind'        => in_array($v['kind'] ?? 'fk', ['candidate', 'manual'], true) ? $v['kind'] : 'fk',
                ];
            }
            $out[$alias] = [$table, $via];
        }
        return $out;
    }

    /** @return list<string> espressioni SELECT con alias di colonna univoci */
    private function columns(array $tables, array $specCols): array
    {
        $select = [];
        $used = [];
        foreach ($specCols as $sc) {
            $alias = (string) ($sc['t'] ?? '');
            $table = $tables[$alias][0] ?? throw HttpException::badRequest('Colonna di una tabella non presente.');
            $col = $this->column($table, (string) ($sc['c'] ?? ''));
            if (in_array($col->category(), ['binary', 'other'], true)) {
                throw HttpException::badRequest("La colonna {$col->name} è di tipo {$col->typeLabel()}: non si può selezionare.");
            }
            $name = isset($used[mb_strtolower($col->name)]) ? $table->name . '_' . $col->name : $col->name;
            $used[mb_strtolower($name)] = true;
            $select[] = "{$alias}.{$col->quoted()}" . ($name !== $col->name ? ' AS ' . Sql::quoteIdent($name) : '');
        }
        if (!$select) {
            throw HttpException::badRequest('Spunta almeno una colonna.');
        }
        return $select;
    }

    /** @return array{0: list<string>, 1: array<string, mixed>} condizioni WHERE e parametri */
    private function filters(array $tables, array $specFilters, bool $forDisplay): array
    {
        $where = [];
        $params = [];
        foreach (array_slice($specFilters, 0, self::MAX_FILTERS) as $i => $f) {
            $alias = (string) ($f['t'] ?? '');
            $table = $tables[$alias][0] ?? throw HttpException::badRequest('Filtro su una tabella non presente.');
            $col = $this->column($table, (string) ($f['c'] ?? ''));
            $op = (string) ($f['op'] ?? '=');
            if (!isset(self::OPERATORS[$op])) {
                throw HttpException::badRequest('Operatore di filtro non valido.');
            }
            $lhs = "{$alias}.{$col->quoted()}";
            if ($op === 'null' || $op === 'notnull') {
                $where[] = $lhs . ($op === 'null' ? ' IS NULL' : ' IS NOT NULL');
                continue;
            }
            $value = (string) ($f['v'] ?? '');
            $isLike = $op === 'contains' || $op === 'starts';
            if ($isLike) {
                $value = Sql::likePattern($value, $op === 'contains' ? 'contains' : 'starts');
            }
            $numeric = !$isLike && in_array($col->category(), ['int', 'number'], true);
            if ($numeric && !is_numeric($value)) {
                throw HttpException::badRequest("Il filtro su {$col->name} richiede un numero.");
            }
            if ($forDisplay) {
                $rhs = $numeric ? $value : Sql::literal($value);
            } else {
                $rhs = ':f' . $i;
                $params['f' . $i] = $value;
            }
            $where[] = $lhs . ($isLike ? ' LIKE ' : " {$op} ") . $rhs;
        }
        return [$where, $params];
    }

    private function column(Table $table, string $name): Column
    {
        return $table->column($name) ?? throw HttpException::badRequest("Colonna non trovata: {$table->fullName}.{$name}");
    }
}
