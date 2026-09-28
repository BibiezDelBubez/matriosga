<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Models\Column;
use App\Models\Table;

/**
 * Lettura righe di una tabella (anteprima, righe che contengono un valore...).
 * Sempre TOP N, identificatori dal Catalog, valori come parametri.
 * La tabella ha sempre alias "t" (le condizioni WHERE passate devono usarlo).
 */
final class TableDataService
{
    /** Tipi che PDO non restituisce bene: convertiti in testo. */
    private const AS_TEXT = ['xml', 'geography', 'geometry', 'hierarchyid', 'sql_variant'];
    private const MAX_CELL = 500;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @param string $where  condizione già validata che usa l'alias t (es. costruita da ValueSearchService)
     * @param string $with   prefisso "WITH p AS (...)" e "CROSS JOIN p" se la condizione usa parametri in CTE
     * @param array<string, mixed> $params
     * @return array{columns: list<array{name: string, type: string}>, rows: list<list<mixed>>, limit: int}
     */
    public function rows(Table $table, string $where = '', array $params = [], string $with = '', ?int $limit = null): array
    {
        $limit ??= (int) $this->settings->get('limits.preview_rows', 50);
        $columns = $table->columns();
        $sql = ($with !== '' ? $with . ' ' : '')
            . 'SELECT TOP (' . max(1, $limit) . ') ' . implode(', ', array_map([$this, 'expression'], $columns))
            . ' FROM ' . $table->quoted() . ' AS t' . ($with !== '' ? ' CROSS JOIN p' : '')
            . ($where !== '' ? ' WHERE ' . $where : '');

        $rows = [];
        foreach ($this->db->select($sql, $params) as $row) {
            $rows[] = array_map([$this, 'cell'], array_values($row));
        }
        return [
            'columns' => array_map(static fn (Column $c) => ['name' => $c->name, 'type' => $c->typeLabel()], $columns),
            'rows'    => $rows,
            'limit'   => $limit,
        ];
    }

    /**
     * Esegue una SELECT già costruita e validata (es. dal costruttore di query) e formatta le celle.
     * @param array<string, mixed> $params
     * @return array{columns: list<array{name: string, type: string}>, rows: list<list<mixed>>, limit: int}
     */
    public function run(string $sql, array $params, int $limit): array
    {
        $raw = $this->db->select($sql, $params);
        return [
            'columns' => array_map(static fn ($name) => ['name' => (string) $name, 'type' => ''], array_keys($raw[0] ?? [])),
            'rows'    => array_map(fn (array $r) => array_map([$this, 'cell'], array_values($r)), $raw),
            'limit'   => $limit,
        ];
    }

    private function expression(Column $c): string
    {
        $col = 't.' . $c->quoted();
        if (in_array($c->type, self::AS_TEXT, true)) {
            return 'CAST(' . $col . ' AS nvarchar(' . self::MAX_CELL . ')) AS ' . $c->quoted();
        }
        if ($c->category() === 'binary') {
            return 'CAST(NULL AS varchar(1)) AS ' . $c->quoted(); // binari non mostrati
        }
        return $col . ' AS ' . $c->quoted();
    }

    private function cell(mixed $value): mixed
    {
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                return '(binario)';
            }
            return mb_strlen($value) > self::MAX_CELL ? mb_substr($value, 0, self::MAX_CELL) . '…' : $value;
        }
        return $value;
    }
}
