<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\HttpException;
use App\Helpers\Sql;
use Closure;

/**
 * Struttura del database in memoria. Il "core" (oggetti, PK, FK) è sempre caricato;
 * indice colonne e dettaglio delle singole tabelle vengono caricati solo se servono
 * (tramite $loader fornito da MetadataCache).
 *
 * È anche il VALIDATORE degli identificatori: un nome di tabella/colonna arrivato
 * dall'utente si usa in SQL solo dopo essere stato risolto qui.
 */
final class Catalog
{
    /** @var array<string, string> nome minuscolo "schema.tabella" => nome reale */
    private array $byFull = [];
    /** @var array<string, list<string>> nome minuscolo senza schema => nomi reali */
    private array $byName = [];
    /** @var array<string, Table> */
    private array $tables = [];
    /** @var array<string, list<array>>|null */
    private ?array $columnIndex = null;
    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $fkFrom = null;
    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $fkTo = null;

    /**
     * @param array<string, mixed> $core   dati di core.json (FK già in forma associativa)
     * @param Closure(string, string=): array $loader ('columns') oppure ('table', 'schema.tabella')
     */
    public function __construct(
        private readonly array $core,
        private readonly Closure $loader,
        private readonly string $defaultSchema = 'dbo',
    ) {
        foreach ($core['objects'] as $full => $o) {
            $this->byFull[mb_strtolower($full)] = $full;
            $this->byName[mb_strtolower($o['name'])][] = $full;
        }
    }

    public function cachedAt(): string
    {
        return (string) $this->core['cached_at'];
    }

    /** @return array<string, mixed> */
    public function server(): array
    {
        return $this->core['server'];
    }

    /** @return array<string, mixed> conteggi precalcolati per la dashboard */
    public function stats(): array
    {
        return $this->core['stats'];
    }

    /** @return array<string, array<string, mixed>> oggetti (tabelle e viste) con pk e ncols */
    public function objects(): array
    {
        return $this->core['objects'];
    }

    /**
     * Indice compatto di tutte le colonne, per le ricerche: full => [[nome, tipo, max_length, precision, scale, nullable], ...]
     * (posizioni: costanti MetadataCache::C_*). Caricato al primo uso.
     * @return array<string, list<array>>
     */
    public function columnIndex(): array
    {
        return $this->columnIndex ??= ($this->loader)('columns');
    }

    /** Accetta "schema.tabella", "[schema].[tabella]" o solo "tabella" (preferendo lo schema predefinito). */
    public function resolve(string $name): ?string
    {
        $name = mb_strtolower(str_replace(['[', ']'], '', trim($name)));
        if ($name === '') {
            return null;
        }
        if (isset($this->byFull[$name])) {
            return $this->byFull[$name];
        }
        $candidates = $this->byName[$name] ?? [];
        foreach ($candidates as $full) {
            if (mb_strtolower($this->core['objects'][$full]['schema']) === mb_strtolower($this->defaultSchema)) {
                return $full;
            }
        }
        return $candidates[0] ?? null;
    }

    /** Nome SQL quotato [schema].[tabella] di un nome già risolto, senza caricare il dettaglio. */
    public function quoted(string $full): string
    {
        $o = $this->core['objects'][$full] ?? throw HttpException::notFound("Tabella o vista non trovata: {$full}");
        return Sql::table($o['schema'], $o['name']);
    }

    public function table(string $name): ?Table
    {
        $full = $this->resolve($name);
        if ($full === null) {
            return null;
        }
        if (!isset($this->tables[$full])) {
            $detail = ($this->loader)('table', $full);
            $this->tables[$full] = new Table($this, $full, $this->core['objects'][$full], $detail['columns'], $detail['indexes']);
        }
        return $this->tables[$full];
    }

    /** Come table() ma con errore 404 leggibile se il nome non esiste. */
    public function require(string $name): Table
    {
        return $this->table($name) ?? throw HttpException::notFound("Tabella o vista non trovata: {$name}");
    }

    /** @return list<array<string, mixed>> */
    public function fks(): array
    {
        return $this->core['fks'];
    }

    /** @return list<array<string, mixed>> FK dichiarate in uscita da una tabella */
    public function fksFrom(string $full): array
    {
        $this->indexFks();
        return $this->fkFrom[$full] ?? [];
    }

    /** @return list<array<string, mixed>> FK di altre tabelle che puntano a questa */
    public function fksTo(string $full): array
    {
        $this->indexFks();
        return $this->fkTo[$full] ?? [];
    }

    /** @return list<string> colonne della PK (in ordine) senza caricare il dettaglio tabella */
    public function pkOf(string $full): array
    {
        return $this->core['objects'][$full]['pk'] ?? [];
    }

    /** @return array<string, list<string>> colonna => ["schema.tabella.colonna" puntate] per le FK in uscita */
    public function fkTargetsOf(string $full): array
    {
        $map = [];
        foreach ($this->fksFrom($full) as $fk) {
            foreach ($fk['from_cols'] as $i => $col) {
                $map[$col][] = $fk['to'] . '.' . $fk['to_cols'][$i];
            }
        }
        return $map;
    }

    /**
     * Riepilogo compatto di tutte le tabelle/viste (per l'elenco tabelle, reso lato client).
     * Formato riga: [nome, tipo U/V, righe, n.colonne, "pk1, pk2", fk uscenti, fk entranti, descrizione]
     * @return list<list<mixed>>
     */
    public function summaries(): array
    {
        $out = [];
        foreach ($this->core['objects'] as $full => $o) {
            $out[] = [$full, $o['type'], $o['rows'], $o['ncols'], implode(', ', $o['pk']), count($this->fksFrom($full)), count($this->fksTo($full)), (string) $o['description']];
        }
        return $out;
    }

    private function indexFks(): void
    {
        if ($this->fkFrom !== null) {
            return;
        }
        $this->fkFrom = $this->fkTo = [];
        foreach ($this->core['fks'] as $fk) {
            $this->fkFrom[$fk['from']][] = $fk;
            $this->fkTo[$fk['to']][] = $fk;
        }
    }
}
