<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Helpers\Sql;
use App\Models\Catalog;
use App\Models\Column;
use App\Models\Table;

/**
 * Genera testo SQL e Power Query (M) da MOSTRARE e COPIARE. Nulla di ciò che produce
 * viene eseguito da Matriosga.
 */
final class QueryTextService
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /** @param list<Column>|null $columns null = tutte */
    public function select(Table $table, ?array $columns = null, ?int $top = null, string $where = ''): string
    {
        $columns = $columns ?: $table->columns();
        $list = implode(",\n    ", array_map(static fn (Column $c) => $c->quoted(), $columns));
        return 'SELECT' . ($top !== null ? " TOP ({$top})" : '') . "\n    {$list}\nFROM " . $table->quoted()
            . ($where !== '' ? "\nWHERE {$where}" : '');
    }

    /**
     * JOIN che segue una FK (dichiarata o candidata): figlio LEFT JOIN padre.
     * @param array{from: string, to: string, from_cols: list<string>, to_cols: list<string>} $fk
     */
    public function join(Catalog $catalog, array $fk): string
    {
        $on = [];
        foreach ($fk['from_cols'] as $i => $col) {
            $on[] = 'p.' . Sql::quoteIdent($fk['to_cols'][$i]) . ' = f.' . Sql::quoteIdent($col);
        }
        return "SELECT\n    f.*,\n    p.*\nFROM {$catalog->quoted($fk['from'])} AS f\nLEFT JOIN {$catalog->quoted($fk['to'])} AS p\n    ON " . implode("\n   AND ", $on);
    }

    /** Commento SQL accanto a una JOIN che non è una FK dichiarata. */
    public static function kindComment(string $kind): string
    {
        return match ($kind) {
            'candidate' => '  -- relazione CANDIDATA (non FK): verificarla',
            'manual'    => '  -- relazione definita da te (non FK)',
            default     => '',
        };
    }

    /**
     * JOIN per una lista di relazioni (stesso indice della lista).
     * @param list<array<string, mixed>> $relations
     * @return list<string>
     */
    public function joins(Catalog $catalog, array $relations): array
    {
        return array_map(fn (array $r) => $this->join($catalog, $r), array_values($relations));
    }

    /**
     * Catena di JOIN lungo un percorso (PathFinderService): t0 LEFT JOIN t1 LEFT JOIN t2 ...
     * @param list<array{a: string, b: string, rel: array, cols_a: list<string>, cols_b: list<string>}> $steps
     */
    public function joinPath(Catalog $catalog, array $steps): string
    {
        $aliases = ['t0.*'];
        $sql = 'FROM ' . $catalog->quoted($steps[0]['a']) . ' AS t0';
        foreach ($steps as $i => $s) {
            $a = 't' . $i;
            $b = 't' . ($i + 1);
            $aliases[] = $b . '.*';
            $on = [];
            foreach ($s['cols_a'] as $k => $col) {
                $on[] = "{$b}." . Sql::quoteIdent($s['cols_b'][$k]) . " = {$a}." . Sql::quoteIdent($col);
            }
            $note = self::kindComment($s['rel']['kind']);
            $sql .= "\nLEFT JOIN " . $catalog->quoted($s['b']) . " AS {$b}{$note}\n    ON " . implode("\n   AND ", $on);
        }
        return "SELECT\n    " . implode(",\n    ", $aliases) . "\n" . $sql;
    }

    /**
     * Power Query "a navigazione" (consigliato: mantiene il query folding).
     * @param list<Column>|null $columns
     */
    public function powerQueryTable(Table $table, ?array $columns = null): string
    {
        $steps = [
            $this->mHeader(),
            '    Source = Sql.Database(Server, Database),',
            '    Data = Source{[Schema = ' . self::m($table->schema) . ', Item = ' . self::m($table->name) . ']}[Data]',
        ];
        $last = 'Data';
        if ($columns) {
            $steps[count($steps) - 1] .= ',';
            $names = implode(', ', array_map(static fn (Column $c) => self::m($c->name), $columns));
            $steps[] = "    Columns = Table.SelectColumns(Data, {{$names}})";
            $last = 'Columns';
        }
        return "let\n" . implode("\n", $steps) . "\nin\n    {$last}";
    }

    /**
     * Power Query con query SQL nativa. L'SQL è scritto una riga per riga (Text.Combine), così resta
     * leggibile e modificabile anche nell'Editor avanzato di Power BI.
     */
    public function powerQueryNative(string $sql): string
    {
        $lines = array_map(static fn (string $l) => '            ' . self::m($l), preg_split('/\R/', $sql) ?: [$sql]);
        return "let\n" . $this->mHeader() . "\n"
            . "    Query = Text.Combine({\n" . implode(",\n", $lines) . "\n        }, \"#(lf)\"),\n"
            . "    Source = Sql.Database(Server, Database, [Query = Query])\nin\n    Source";
    }

    /** Parametri in testa: sostituibili a mano o trasformabili in parametri Power BI. */
    private function mHeader(): string
    {
        $server = (string) $this->settings->get('connection.server');
        $port = (string) $this->settings->get('connection.port');
        return '    // Sostituire con parametri Power BI se necessario' . "\n"
            . '    Server = ' . self::m($server . ($port !== '' ? ',' . $port : '')) . ",\n"
            . '    Database = ' . self::m((string) $this->settings->get('connection.database')) . ',';
    }

    /** Letterale di testo M: raddoppia le virgolette, a capo come #(lf). */
    private static function m(string $value): string
    {
        return '"' . str_replace(['#(', '"', "\r\n", "\n", "\t"], ['#(#)(', '""', '#(lf)', '#(lf)', '#(tab)'], $value) . '"';
    }
}
