<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Legge la struttura del database dai cataloghi ufficiali sys.* con poche query set-based
 * e produce uno "snapshot" (array serializzabile in JSON) usato da Catalog.
 * Nessuna query specifica di SGA: funziona con qualunque database SQL Server.
 */
final class MetadataService
{
    public const SNAPSHOT_VERSION = 3;
    private const TIMEOUT = 180;

    /** Filtro comune: tabelle utente e viste, esclusi oggetti di sistema. */
    private const USER_OBJECTS = "o.type IN ('U','V') AND o.is_ms_shipped = 0";

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $start = microtime(true);
        $server = $this->db->serverInfo();

        $names = [];    // object_id => "schema.nome"
        $objects = [];
        foreach ($this->queryObjects() as $r) {
            $full = $r['schema_name'] . '.' . $r['name'];
            $names[$r['object_id']] = $full;
            $objects[$full] = [
                'schema'      => $r['schema_name'],
                'name'        => $r['name'],
                'type'        => trim($r['type']) === 'V' ? 'V' : 'U',
                'rows'        => $r['row_count'] === null ? null : (int) $r['row_count'],
                'description' => $r['description'],
                'modified'    => substr((string) $r['modify_date'], 0, 19),
            ];
        }

        $columns = [];
        foreach ($this->queryColumns() as $r) {
            $full = $names[$r['object_id']] ?? null;
            if ($full === null) {
                continue;
            }
            $columns[$full][] = [
                'id'          => (int) $r['column_id'],
                'name'        => $r['name'],
                'type'        => $r['type_name'],
                'user_type'   => $r['user_type'],
                'max_length'  => (int) $r['max_length'],
                'precision'   => (int) $r['precision'],
                'scale'       => (int) $r['scale'],
                'nullable'    => (bool) $r['is_nullable'],
                'identity'    => (bool) $r['is_identity'],
                'computed'    => (bool) $r['is_computed'],
                'default'     => $r['default_definition'],
                'collation'   => $r['collation_name'],
                'description' => $r['description'],
            ];
        }

        $indexes = [];
        foreach ($this->queryIndexColumns() as $r) {
            $full = $names[$r['object_id']] ?? null;
            if ($full === null) {
                continue;
            }
            $key = (int) $r['index_id'];
            $indexes[$full][$key] ??= [
                'name'     => $r['name'],
                'type'     => $r['type_desc'],
                'unique'   => (bool) $r['is_unique'],
                'pk'       => (bool) $r['is_primary_key'],
                'uq'       => (bool) $r['is_unique_constraint'],
                'columns'  => [],
                'included' => [],
            ];
            $indexes[$full][$key][$r['is_included_column'] ? 'included' : 'columns'][] = $r['column_name'];
        }
        $indexes = array_map('array_values', $indexes);

        $fks = [];
        foreach ($this->queryForeignKeys() as $r) {
            $id = (int) $r['fk_id'];
            $fks[$id] ??= [
                'name'      => $r['name'],
                'from'      => $names[$r['parent_object_id']] ?? null,
                'to'        => $names[$r['referenced_object_id']] ?? null,
                'from_cols' => [],
                'to_cols'   => [],
                'on_delete' => $r['on_delete'],
                'on_update' => $r['on_update'],
                'disabled'  => (bool) $r['is_disabled'],
                'trusted'   => !$r['is_not_trusted'],
            ];
            $fks[$id]['from_cols'][] = $r['parent_col'];
            $fks[$id]['to_cols'][] = $r['ref_col'];
        }
        $fks = array_values(array_filter($fks, static fn (array $fk) => $fk['from'] !== null && $fk['to'] !== null));

        return [
            'version'   => self::SNAPSHOT_VERSION,
            'cached_at' => date('Y-m-d H:i:s'),
            'seconds'   => round(microtime(true) - $start, 2),
            'server'    => $server,
            'objects'   => $objects,
            'columns'   => $columns,
            'indexes'   => $indexes,
            'fks'       => $fks,
            'stats'     => self::stats($objects, $columns, $indexes, $fks),
        ];
    }

    /**
     * Conteggi calcolati una volta sola alla lettura (la dashboard non deve caricare le colonne).
     * @return array<string, mixed>
     */
    public static function stats(array $objects, array $columns, array $indexes, array $fks): array
    {
        $stats = ['tables' => 0, 'views' => 0, 'columns' => 0, 'pks' => 0, 'fks' => count($fks), 'indexes' => 0, 'schemas' => []];
        foreach ($objects as $full => $o) {
            $o['type'] === 'V' ? $stats['views']++ : $stats['tables']++;
            $stats['schemas'][$o['schema']] = ($stats['schemas'][$o['schema']] ?? 0) + 1;
            $stats['columns'] += count($columns[$full] ?? []);
        }
        foreach ($indexes as $list) {
            foreach ($list as $index) {
                $index['pk'] ? $stats['pks']++ : $stats['indexes']++;
            }
        }
        ksort($stats['schemas']);
        return $stats;
    }

    /** @return list<array<string, mixed>> */
    private function queryObjects(): array
    {
        return $this->db->select(
            "SELECT o.object_id, s.name AS schema_name, o.name, o.type, o.modify_date,
                    p.row_count, CAST(ep.value AS nvarchar(4000)) AS description
             FROM sys.objects o
             JOIN sys.schemas s ON s.schema_id = o.schema_id
             LEFT JOIN (SELECT object_id, SUM(rows) AS row_count
                        FROM sys.partitions WHERE index_id IN (0, 1) GROUP BY object_id) p ON p.object_id = o.object_id
             LEFT JOIN sys.extended_properties ep
                    ON ep.class = 1 AND ep.major_id = o.object_id AND ep.minor_id = 0 AND ep.name = 'MS_Description'
             WHERE " . self::USER_OBJECTS . "
             ORDER BY s.name, o.name",
            [],
            self::TIMEOUT,
        );
    }

    /** @return list<array<string, mixed>> */
    private function queryColumns(): array
    {
        return $this->db->select(
            "SELECT c.object_id, c.column_id, c.name,
                    TYPE_NAME(c.system_type_id) AS type_name,
                    CASE WHEN t.is_user_defined = 1 THEN t.name END AS user_type,
                    c.max_length, c.precision, c.scale, c.is_nullable, c.is_identity, c.is_computed, c.collation_name,
                    dc.definition AS default_definition,
                    CAST(ep.value AS nvarchar(4000)) AS description
             FROM sys.columns c
             JOIN sys.objects o ON o.object_id = c.object_id
             JOIN sys.types t ON t.user_type_id = c.user_type_id
             LEFT JOIN sys.default_constraints dc ON dc.object_id = c.default_object_id
             LEFT JOIN sys.extended_properties ep
                    ON ep.class = 1 AND ep.major_id = c.object_id AND ep.minor_id = c.column_id AND ep.name = 'MS_Description'
             WHERE " . self::USER_OBJECTS . "
             ORDER BY c.object_id, c.column_id",
            [],
            self::TIMEOUT,
        );
    }

    /** @return list<array<string, mixed>> */
    private function queryIndexColumns(): array
    {
        return $this->db->select(
            "SELECT i.object_id, i.index_id, i.name, i.type_desc, i.is_unique, i.is_primary_key, i.is_unique_constraint,
                    ic.is_included_column, c.name AS column_name
             FROM sys.indexes i
             JOIN sys.objects o ON o.object_id = i.object_id
             JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
             JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
             WHERE " . self::USER_OBJECTS . " AND i.type > 0 AND i.is_hypothetical = 0
             ORDER BY i.object_id, i.index_id, ic.is_included_column, ic.key_ordinal, ic.index_column_id",
            [],
            self::TIMEOUT,
        );
    }

    /** @return list<array<string, mixed>> */
    private function queryForeignKeys(): array
    {
        return $this->db->select(
            "SELECT fk.object_id AS fk_id, fk.name, fk.parent_object_id, fk.referenced_object_id,
                    fk.delete_referential_action_desc AS on_delete, fk.update_referential_action_desc AS on_update,
                    fk.is_disabled, fk.is_not_trusted,
                    pc.name AS parent_col, rc.name AS ref_col
             FROM sys.foreign_keys fk
             JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
             JOIN sys.columns pc ON pc.object_id = fkc.parent_object_id AND pc.column_id = fkc.parent_column_id
             JOIN sys.columns rc ON rc.object_id = fkc.referenced_object_id AND rc.column_id = fkc.referenced_column_id
             ORDER BY fk.object_id, fkc.constraint_column_id",
            [],
            self::TIMEOUT,
        );
    }
}
