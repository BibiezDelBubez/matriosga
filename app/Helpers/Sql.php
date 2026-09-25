<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Unico punto per quoting identificatori ed escape dei pattern LIKE.
 * Gli identificatori vanno comunque VALIDATI prima contro il Catalog.
 */
final class Sql
{
    /** Modalità di confronto testuale (ricerca valore e ricerca colonne) con etichetta UI. */
    public const MATCH_MODES = ['contains' => 'Contiene', 'equals' => 'Uguale a', 'starts' => 'Inizia con', 'ends' => 'Termina con'];

    public static function quoteIdent(string $name): string
    {
        return '[' . str_replace(']', ']]', $name) . ']';
    }

    public static function table(string $schema, string $table): string
    {
        return self::quoteIdent($schema) . '.' . self::quoteIdent($table);
    }

    /** Escape dei caratteri speciali di LIKE in T-SQL (senza clausola ESCAPE). */
    public static function likeEscape(string $value): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
    }

    /** Pattern LIKE per la modalità richiesta (contains|equals|starts|ends). */
    public static function likePattern(string $value, string $mode): string
    {
        $v = self::likeEscape($value);
        return match ($mode) {
            'equals' => $v,
            'starts' => $v . '%',
            'ends'   => '%' . $v,
            default  => '%' . $v . '%',
        };
    }

    /** Stringa T-SQL letterale (solo per SQL mostrato all'utente, mai eseguito). */
    public static function literal(string $value): string
    {
        return "N'" . str_replace("'", "''", $value) . "'";
    }
}
