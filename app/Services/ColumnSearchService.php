<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Catalog;
use App\Models\Column;

/** Ricerca colonne per nome: solo metadata in memoria, nessuna query al database. */
final class ColumnSearchService
{
    /** Oltre questo numero di colonne trovate si chiede di restringere la ricerca. */
    public const MAX_MATCHES = 20000;

    /**
     * Tutte le colonne trovate (fino a MAX_MATCHES), ordinate per nome colonna e tabella: la paginazione la fa il controller.
     * @return array{rows: list<array<string, mixed>>, total: int, tables: int, truncated: bool}
     */
    public function search(Catalog $catalog, string $query, string $mode, string $category = '', bool $views = true): array
    {
        $needle = mb_strtolower(trim($query));
        $objects = $catalog->objects();
        $rows = [];
        $tables = [];
        $total = 0;

        foreach ($catalog->columnIndex() as $full => $columns) {
            $isView = $objects[$full]['type'] === 'V';
            if ($isView && !$views) {
                continue;
            }
            $fk = null;
            foreach ($columns as $c) {
                [$name, $type] = [$c[MetadataCache::C_NAME], $c[MetadataCache::C_TYPE]];
                if (!self::matches(mb_strtolower($name), $needle, $mode)
                    || ($category !== '' && Column::categoryOf($type) !== $category)) {
                    continue;
                }
                $total++;
                $tables[$full] = true;
                if (count($rows) >= self::MAX_MATCHES) {
                    continue;
                }
                $fk ??= $catalog->fkTargetsOf($full);
                $rows[] = [
                    'table'    => $full,
                    'view'     => $isView,
                    'column'   => $name,
                    'type'     => Column::label($type, $c[MetadataCache::C_LEN], $c[MetadataCache::C_PREC], $c[MetadataCache::C_SCALE]),
                    'nullable' => $c[MetadataCache::C_NULL],
                    'pk'       => in_array($name, $objects[$full]['pk'], true),
                    'fk'       => $fk[$name] ?? [],
                    'rows'     => $objects[$full]['rows'],
                ];
            }
        }
        usort($rows, static fn (array $a, array $b) => [mb_strtolower($a['column']), $a['table']] <=> [mb_strtolower($b['column']), $b['table']]);

        return ['rows' => $rows, 'total' => $total, 'tables' => count($tables), 'truncated' => $total > count($rows)];
    }

    private static function matches(string $name, string $needle, string $mode): bool
    {
        return match ($mode) {
            'equals' => $name === $needle,
            'starts' => str_starts_with($name, $needle),
            'ends'   => str_ends_with($name, $needle),
            default  => str_contains($name, $needle),
        };
    }
}
