<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Settings;
use App\Models\Column;
use App\Models\Table;

/**
 * Profilo di una colonna: conteggi, NULL, distinti, min/max, lunghezze, valori più frequenti, distribuzione.
 * Sulle tabelle molto grandi lavora su un campione (TOP N righe) salvo richiesta esplicita.
 */
final class ColumnAnalysisService
{
    private const DEFAULT_SAMPLE = 1000000;
    private const SUPPORTED = ['string', 'text', 'int', 'number', 'guid', 'date'];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly Settings $settings,
        private readonly QueryTextService $queryText,
    ) {
    }

    /** @return array<string, mixed> */
    public function analyze(Table $table, Column $col, bool $full): array
    {
        $cat = $col->category();
        if (!in_array($cat, self::SUPPORTED, true)) {
            throw HttpException::badRequest("Il tipo {$col->typeLabel()} non si può analizzare (binario o non confrontabile).");
        }
        set_time_limit(0);

        // Campione: impostazione esplicita, oppure automatico sulle tabelle oltre la soglia della ricerca valore.
        $sample = (int) $this->settings->get('limits.analysis_sample_rows', 0);
        if ($sample === 0 && !$full && ($table->rows ?? 0) > (int) $this->settings->get('limits.search_max_table_rows')) {
            $sample = self::DEFAULT_SAMPLE;
        }
        $value = match (true) {
            $cat === 'text'       => 'CAST(t.' . $col->quoted() . ' AS nvarchar(4000))',
            $col->type === 'bit'  => 'CAST(t.' . $col->quoted() . ' AS tinyint)',
            default               => 't.' . $col->quoted(),
        };
        $src = '(SELECT ' . ($sample > 0 ? "TOP ({$sample}) " : '') . "{$value} AS v FROM {$table->quoted()} AS t) AS s";

        // 1) Statistiche generali (una sola scansione)
        $fields = ['COUNT_BIG(*) AS total', 'COUNT_BIG(s.v) AS non_null', 'COUNT_BIG(DISTINCT s.v) AS distinct_count'];
        if ($cat !== 'guid') {
            $fields[] = 'MIN(s.v) AS min_value';
            $fields[] = 'MAX(s.v) AS max_value';
        }
        if (in_array($cat, ['int', 'number'], true)) {
            $fields[] = 'AVG(CAST(s.v AS float)) AS avg_value';
        }
        if (in_array($cat, ['string', 'text'], true)) {
            $fields[] = 'MIN(LEN(s.v)) AS min_len';
            $fields[] = 'MAX(LEN(s.v)) AS max_len';
            $fields[] = 'AVG(CAST(LEN(s.v) AS float)) AS avg_len';
            $fields[] = "SUM(CASE WHEN LTRIM(RTRIM(s.v)) = '' THEN 1 ELSE 0 END) AS empty_count";
        }
        $stats = $this->db->selectOne('SELECT ' . implode(', ', $fields) . ' FROM ' . $src) ?? [];
        $total = (int) ($stats['total'] ?? 0);

        // 2) Valori più frequenti (NULL compreso)
        $limit = max(1, (int) $this->settings->get('limits.analysis_top_values', 50));
        $top = array_map(static fn (array $r) => [
            'value' => $r['value'],
            'n'     => (int) $r['n'],
            'pct'   => $total ? round($r['n'] / $total * 100, 2) : 0,
        ], $this->db->select("SELECT TOP ({$limit}) s.v AS value, COUNT_BIG(*) AS n FROM {$src} GROUP BY s.v ORDER BY n DESC, s.v"));

        $sqlText = "SELECT\n    {$col->quoted()},\n    COUNT(*) AS n\nFROM {$table->quoted()}\nGROUP BY {$col->quoted()}\nORDER BY n DESC";
        return [
            'table'        => $table->fullName,
            'column'       => $col->name,
            'type'         => $col->typeLabel(),
            'category'     => $cat,
            'sample'       => $sample,
            'table_rows'   => $table->rows,
            'stats'        => $stats,
            'top'          => $top,
            'top_limit'    => $limit,
            'distribution' => $this->distribution($cat, $src, $stats),
            'sql'          => $sqlText,
            'pq'           => $this->queryText->powerQueryNative($sqlText),
        ];
    }

    /**
     * Distribuzione: date per anno, numeri in 10 fasce, testi per lunghezza.
     * @return array{kind: string, rows: list<array{label: string, n: int}>}|null
     */
    private function distribution(string $cat, string $src, array $stats): ?array
    {
        if ($cat === 'date') {
            $rows = $this->db->select("SELECT TOP (200) YEAR(s.v) AS label, COUNT_BIG(*) AS n FROM {$src} WHERE s.v IS NOT NULL GROUP BY YEAR(s.v) ORDER BY label");
            return ['kind' => 'Anno', 'rows' => self::rows($rows)];
        }
        if (in_array($cat, ['string', 'text'], true)) {
            $rows = $this->db->select("SELECT TOP (60) LEN(s.v) AS label, COUNT_BIG(*) AS n FROM {$src} WHERE s.v IS NOT NULL GROUP BY LEN(s.v) ORDER BY label");
            return ['kind' => 'Lunghezza (caratteri)', 'rows' => self::rows($rows)];
        }
        if (in_array($cat, ['int', 'number'], true) && isset($stats['min_value'], $stats['max_value'])) {
            $min = (float) $stats['min_value'];
            $max = (float) $stats['max_value'];
            if ($max <= $min || (int) $stats['distinct_count'] <= 10) {
                return null; // pochi valori: bastano i più frequenti
            }
            // min e ampiezza vengono dal database e sono numeri: letterali sicuri
            $mn = sprintf('%.17g', $min);
            $w = sprintf('%.17g', ($max - $min) / 10);
            $bucket = "CASE WHEN FLOOR((CAST(s.v AS float) - {$mn}) / {$w}) >= 10 THEN 9 ELSE FLOOR((CAST(s.v AS float) - {$mn}) / {$w}) END";
            $rows = $this->db->select("SELECT x.b AS b, COUNT_BIG(*) AS n FROM (SELECT {$bucket} AS b FROM {$src} WHERE s.v IS NOT NULL) AS x GROUP BY x.b ORDER BY x.b");
            $step = ($max - $min) / 10;
            return ['kind' => 'Fascia di valori', 'rows' => array_map(static fn (array $r) => [
                'label' => self::num($min + $r['b'] * $step) . ' – ' . self::num($min + ($r['b'] + 1) * $step),
                'n'     => (int) $r['n'],
            ], $rows)];
        }
        return null;
    }

    /** @return list<array{label: string, n: int}> */
    private static function rows(array $rows): array
    {
        return array_map(static fn (array $r) => ['label' => (string) $r['label'], 'n' => (int) $r['n']], $rows);
    }

    private static function num(float $n): string
    {
        return abs($n) >= 100 ? number_format($n, 0, ',', '.') : rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}
