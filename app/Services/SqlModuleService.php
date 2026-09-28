<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Helpers\Sql;
use App\Helpers\Text;

/**
 * "Query di SGA": viste, funzioni, trigger e procedure scritti dal fornitore dentro il database.
 * Cercarci un nome di tabella/colonna mostra come il gestionale stesso usa e collega quei dati.
 * Serve il permesso VIEW DEFINITION: senza, SQL Server restituisce il testo vuoto.
 */
final class SqlModuleService
{
    /** Tipi con spiegazione per chi non è un DBA. */
    public const TYPES = [
        'VIEW'                             => ['Vista', 'Una query salvata di SGA: si usa come una tabella, anche in Power BI.'],
        'SQL_TABLE_VALUED_FUNCTION'        => ['Funzione tabella', 'Una query con parametri: restituisce righe come una tabella.'],
        'SQL_INLINE_TABLE_VALUED_FUNCTION' => ['Funzione tabella', 'Una query con parametri: restituisce righe come una tabella.'],
        'SQL_SCALAR_FUNCTION'              => ['Funzione', 'Calcola un singolo valore (es. un totale, una descrizione).'],
        'SQL_TRIGGER'                      => ['Trigger', 'Codice che SGA esegue da solo quando una tabella cambia: mostra quali altre tabelle aggiorna.'],
        'SQL_STORED_PROCEDURE'             => ['Procedura', 'Un programma SQL di SGA (elaborazioni, stampe, calcoli).'],
    ];
    private const MAX_RESULTS = 500;
    private const SNIPPETS = 3;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /** @return array{total: int, readable: int} quante query di SGA esistono e quante il nostro utente può leggere */
    public function access(): array
    {
        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN m.definition IS NOT NULL THEN 1 ELSE 0 END) AS readable
             FROM sys.sql_modules m JOIN sys.objects o ON o.object_id = m.object_id WHERE o.is_ms_shipped = 0'
        ) ?? [];
        return ['total' => (int) ($row['total'] ?? 0), 'readable' => (int) ($row['readable'] ?? 0)];
    }

    /** Login SQL in uso (per la riga GRANT da girare al DBA). */
    public function login(): string
    {
        return (string) ($this->db->selectOne('SELECT USER_NAME() AS u')['u'] ?? '');
    }

    /**
     * Query di SGA che contengono il testo. $wholeWord: "BaCliFor" non trova "BaCliForColonneLibere".
     * @return list<array{name: string, type: string, label: string, help: string, parent: ?string, hits: int, snippets: list<string>}>
     */
    public function search(string $text, bool $wholeWord): array
    {
        $text = trim(str_replace(['[', ']'], '', $text));
        if (mb_strlen($text) < 3) {
            throw HttpException::badRequest('Scrivi almeno 3 caratteri (es. il nome di una tabella o di una colonna).');
        }
        $rows = $this->db->select(
            'SELECT TOP (' . self::MAX_RESULTS . ') OBJECT_SCHEMA_NAME(o.object_id) + \'.\' + o.name AS name, o.type_desc,
                    OBJECT_SCHEMA_NAME(o.parent_object_id) + \'.\' + OBJECT_NAME(o.parent_object_id) AS parent, m.definition
             FROM sys.sql_modules m JOIN sys.objects o ON o.object_id = m.object_id
             WHERE o.is_ms_shipped = 0 AND m.definition LIKE :p
             ORDER BY o.type_desc, o.name',
            ['p' => Sql::likePattern($text, 'contains')],
        );
        $regex = Text::regex($text, $wholeWord);
        $out = [];
        foreach ($rows as $r) {
            $hits = preg_match_all($regex, (string) $r['definition']);
            if (!$hits) {
                continue; // trovata da LIKE ma non come parola intera
            }
            [$label, $help] = self::TYPES[$r['type_desc']] ?? [$r['type_desc'], ''];
            $out[] = [
                'name'     => $r['name'],
                'type'     => $r['type_desc'],
                'label'    => $label,
                'help'     => $help,
                'parent'   => $r['parent'] !== '.' ? $r['parent'] : null,
                'hits'     => $hits,
                'snippets' => self::snippets((string) $r['definition'], $regex),
            ];
        }
        return $out;
    }

    /** @return array{name: string, type: string, definition: string, params: list<string>} */
    public function get(string $name): array
    {
        $row = $this->db->selectOne(
            'SELECT OBJECT_SCHEMA_NAME(o.object_id) + \'.\' + o.name AS name, o.type_desc, m.definition, o.object_id
             FROM sys.sql_modules m JOIN sys.objects o ON o.object_id = m.object_id
             WHERE o.is_ms_shipped = 0 AND o.object_id = OBJECT_ID(:n)',
            ['n' => $name],
        ) ?? throw HttpException::notFound("Query di SGA non trovata: {$name}");
        $params = $this->db->select('SELECT name FROM sys.parameters WHERE object_id = :id AND parameter_id > 0 ORDER BY parameter_id', ['id' => $row['object_id']]);
        return [
            'name'       => $row['name'],
            'type'       => $row['type_desc'],
            'definition' => (string) $row['definition'],
            'params'     => array_column($params, 'name'),
        ];
    }

    /** SQL per usare la query in Power BI: viste e funzioni tabella (con i parametri da compilare), altrimenti null. */
    public static function usageSql(array $module): ?string
    {
        [$schema, $name] = explode('.', $module['name'], 2);
        $object = Sql::table($schema, $name);
        return match ($module['type']) {
            'VIEW' => "SELECT *\nFROM {$object}",
            'SQL_TABLE_VALUED_FUNCTION', 'SQL_INLINE_TABLE_VALUED_FUNCTION' => "SELECT *\nFROM {$object}("
                . implode(', ', array_map(static fn (string $p) => "/* {$p} */ NULL", $module['params'])) . ')',
            default => null,
        };
    }

    /** Righe che contengono il testo, con una riga di contesto prima e dopo. @return list<string> */
    public static function snippets(string $definition, string $regex): array
    {
        $lines = preg_split('/\R/', $definition) ?: [];
        $out = [];
        $used = [];
        foreach ($lines as $i => $line) {
            if (count($out) >= self::SNIPPETS || !preg_match($regex, $line) || isset($used[$i])) {
                continue;
            }
            $from = max(0, $i - 1);
            $to = min(count($lines) - 1, $i + 1);
            $chunk = [];
            for ($k = $from; $k <= $to; $k++) {
                $used[$k] = true;
                $chunk[] = rtrim($lines[$k]);
            }
            $out[] = implode("\n", $chunk);
        }
        return $out;
    }
}
