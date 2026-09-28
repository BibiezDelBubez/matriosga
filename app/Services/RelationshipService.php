<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\Catalog;
use App\Models\Column;

/**
 * Relazioni tra tabelle.
 *  - DEFINITE DALL'UTENTE: collegamenti noti che il DB non dichiara (UserRelationService), priorità massima.
 *  - FK DICHIARATE: dal catalogo SQL Server.
 *  - Relazioni CANDIDATE: dedotte dai metadata, MAI presentate come FK. Due strategie:
 *      'learned' combinazione di colonne che in N FK dichiarate punta sempre alla stessa tabella
 *                (es. SOCIETA+CLIENTE_CLIFOR+CLIENTE → BaCliFor): stessa combinazione altrove = candidata
 *      'name'    colonna con nome derivato da una tabella con PK a colonna singola (ID_Tabella, Tabella_ID…)
 *    Verifica facoltativa sui dati (verify): % di valori campionati che esistono nella tabella puntata.
 */
final class RelationshipService
{
    public const REASONS = [
        'learned' => 'Stesse colonne di FK dichiarate verso questa tabella',
        'name'    => 'Nome colonna derivato dalla tabella puntata',
    ];
    public const HUB_IN = 300;            // tabelle con più FK entranti = "hub" (società, filiali…): nascoste di default
    private const MIN_LEARNED = 2;        // una combinazione deve comparire in almeno N FK dichiarate
    private const MIN_SCORE = 50;         // coerenza minima: la combinazione punta a quella tabella in almeno il 50% dei casi
    private const HUB_SKIP = 1000;        // FK a colonna singola verso tabelle con più FK entranti: ignorate (es. BaSocieta)
    private const VERIFY_SAMPLE = 10000;  // righe campionate nella verifica dati
    private const CAND_KEYS = ['from', 'to', 'from_cols', 'to_cols', 'score', 'evidence', 'reason'];

    /** @var list<array<string, mixed>>|null */
    private ?array $candidates = null;

    public function __construct(
        private readonly MetadataCache $cache,
        private readonly DatabaseService $db,
        private readonly UserRelationService $userRelations,
    ) {
    }

    /**
     * Relazioni definite dall'utente, valide per il catalogo attuale (tabelle ancora esistenti).
     * @return list<array<string, mixed>>
     */
    public function manual(Catalog $catalog): array
    {
        $objects = $catalog->objects();
        return array_values(array_filter(
            array_map(static fn (array $r) => $r + ['kind' => 'manual', 'name' => 'definita da te' . ($r['note'] !== '' ? ': ' . $r['note'] : '')], $this->userRelations->all()),
            static fn (array $r) => isset($objects[$r['from']], $objects[$r['to']]),
        ));
    }

    /**
     * Filtra una lista di relazioni. $query: parole che devono comparire tutte in "da.colonne a.colonne nome";
     * $from / $to: nome esatto (già risolto) della tabella origine/destinazione.
     * Restituisce tutte le relazioni che corrispondono; $limit = 0 senza limite (paginazione nel controller).
     * @param list<array<string, mixed>> $source
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function filter(array $source, string $query, ?string $from, ?string $to, int $limit = 0): array
    {
        $tokens = array_filter(preg_split('/\s+/', mb_strtolower(trim($query))) ?: []);
        $rows = [];
        $total = 0;
        foreach ($source as $r) {
            if (($from !== null && $r['from'] !== $from) || ($to !== null && $r['to'] !== $to)) {
                continue;
            }
            if ($tokens) {
                $text = mb_strtolower($r['from'] . '.' . implode(',', $r['from_cols']) . ' ' . $r['to'] . '.' . implode(',', $r['to_cols']) . ' ' . ($r['name'] ?? ''));
                foreach ($tokens as $t) {
                    if (!str_contains($text, $t)) {
                        continue 2;
                    }
                }
            }
            $total++;
            if ($limit === 0 || count($rows) < $limit) {
                $rows[] = $r;
            }
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Coppie di colonne proposte per collegare $from (che punta) a $to (puntata), per l'editor delle relazioni.
     * Per ogni colonna della PK di $to cerca in $from lo stesso nome oppure PREFISSO_nome (es. CLIENTE_ANNO → ANNO).
     * Un suggerimento per ogni prefisso trovato, i più completi prima. Solo una proposta: l'utente conferma.
     * @return list<array{prefix: string, pairs: list<array{0: string, 1: string}>}>
     */
    public function suggestPairs(Catalog $catalog, string $from, string $to): array
    {
        $a = $catalog->require($from);
        $b = $catalog->require($to);
        $pk = $b->pkColumns();
        if (!$pk) {
            return [];
        }
        $byPrefix = [];
        foreach ($a->columns() as $col) {
            foreach ($pk as $p) {
                $name = mb_strtoupper($col->name);
                $key = mb_strtoupper($p);
                if ($name === $key) {
                    $byPrefix[''][$p] = $col->name;
                } elseif (str_ends_with($name, '_' . $key)) {
                    $byPrefix[mb_substr($col->name, 0, -mb_strlen($p) - 1)][$p] = $col->name;
                }
            }
        }
        $out = [];
        foreach ($byPrefix as $prefix => $found) {
            if ($prefix === '' && count($byPrefix) > 1) {
                continue; // lo stesso nome serve solo a completare gli altri prefissi (es. SOCIETA)
            }
            $pairs = [];
            foreach ($pk as $p) {
                $match = $found[$p] ?? $byPrefix[''][$p] ?? null;
                if ($match !== null) {
                    $pairs[] = [$match, $p];
                }
            }
            $out[] = ['prefix' => (string) $prefix, 'pairs' => $pairs, 'complete' => count($pairs) === count($pk)];
        }
        usort($out, static fn (array $x, array $y) => [$y['complete'], count($y['pairs'])] <=> [$x['complete'], count($x['pairs'])]);
        return array_slice($out, 0, 8);
    }

    /**
     * Toglie le relazioni che toccano tabelle nascoste (vuote/copie, vedi TableFilterService).
     * @param list<array<string, mixed>> $relations
     * @param array<string, true> $hidden
     * @return list<array<string, mixed>>
     */
    public function withoutTables(array $relations, array $hidden): array
    {
        if (!$hidden) {
            return $relations;
        }
        return array_values(array_filter($relations, static fn (array $r) => !isset($hidden[$r['from']]) && !isset($hidden[$r['to']])));
    }

    /** FK dichiarate già ristrette per tabella (usa gli indici del Catalog). @return list<array<string, mixed>> */
    public function declared(Catalog $catalog, ?string $from, ?string $to): array
    {
        return match (true) {
            $from !== null => $catalog->fksFrom($from),
            $to !== null   => $catalog->fksTo($to),
            default        => $catalog->fks(),
        };
    }

    /**
     * Relazioni di una tabella per il suo dettaglio: FK entranti (le prime $limit: le hub ne hanno migliaia)
     * e relazioni candidate in uscita/entrata (le prime $limit ciascuna), senza le tabelle $hidden.
     * @param array<string, true> $hidden
     * @return array{manual: list<array>, fksIn: list<array>, fksInTotal: int, candOut: array{rows: list<array>, total: int}, candIn: array{rows: list<array>, total: int}}
     */
    public function forTable(Catalog $catalog, string $full, int $limit, array $hidden = []): array
    {
        $fksIn = $this->withoutTables($catalog->fksTo($full), $hidden);
        $candidates = $this->withoutTables($this->candidatesFor($catalog, true), $hidden);
        return [
            'manual'     => array_values(array_filter($this->manual($catalog), static fn (array $r) => $r['from'] === $full || $r['to'] === $full)),
            'fksIn'      => array_slice($fksIn, 0, $limit),
            'fksInTotal' => count($fksIn),
            'candOut'    => $this->filter($candidates, '', $full, null, $limit),
            'candIn'     => $this->filter($candidates, '', null, $full, $limit),
        ];
    }

    /** Tabelle più referenziate (hub). @return array<string, int> nome => FK entranti */
    public function hubs(Catalog $catalog, int $limit): array
    {
        $counts = $this->inDegree($catalog);
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * Relazioni candidate, ordinate per punteggio (calcolate una volta e messe in cache).
     * @return list<array{from: string, to: string, from_cols: list<string>, to_cols: list<string>, score: int, evidence: int, reason: string}>
     */
    public function candidates(Catalog $catalog): array
    {
        if ($this->candidates === null) {
            $rows = $this->cache->derived('candidates', fn () => $this->buildCandidates($catalog));
            $this->candidates = array_map(static fn (array $r) => array_combine(self::CAND_KEYS, $r), $rows);
        }
        return $this->candidates;
    }

    /**
     * Verifica sui dati: quante righe (campione, colonne non NULL) della tabella origine
     * trovano corrispondenza nella tabella puntata.
     * @param list<string> $fromCols
     * @param list<string> $toCols
     * @return array{sampled: int, matched: int, pct: float|null, sample: int}
     */
    public function verify(Catalog $catalog, string $from, array $fromCols, string $to, array $toCols): array
    {
        $src = $catalog->require($from);
        $dst = $catalog->require($to);
        if (!$fromCols || count($fromCols) !== count($toCols)) {
            throw HttpException::badRequest('Colonne della relazione non valide.');
        }
        $on = $notNull = [];
        foreach ($fromCols as $i => $name) {
            $fc = $src->column($name) ?? throw HttpException::notFound("Colonna non trovata: {$from}.{$name}");
            $tc = $dst->column($toCols[$i]) ?? throw HttpException::notFound("Colonna non trovata: {$to}.{$toCols[$i]}");
            $notNull[] = 'f.' . $fc->quoted() . ' IS NOT NULL';
            $on[] = 'p.' . $tc->quoted() . ' = f.' . $fc->quoted();
        }
        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS sampled, SUM(x.m) AS matched FROM ('
            . 'SELECT TOP (' . self::VERIFY_SAMPLE . ') CASE WHEN EXISTS (SELECT 1 FROM ' . $dst->quoted() . ' AS p WHERE ' . implode(' AND ', $on) . ') THEN 1 ELSE 0 END AS m'
            . ' FROM ' . $src->quoted() . ' AS f WHERE ' . implode(' AND ', $notNull) . ') AS x'
        ) ?? [];
        $sampled = (int) ($row['sampled'] ?? 0);
        $matched = (int) ($row['matched'] ?? 0);
        return ['sampled' => $sampled, 'matched' => $matched, 'pct' => $sampled ? round($matched / $sampled * 100, 1) : null, 'sample' => self::VERIFY_SAMPLE];
    }

    /** @return list<list<mixed>> righe compatte (CAND_KEYS) */
    private function buildCandidates(Catalog $catalog): array
    {
        $index = $catalog->columnIndex();
        $inDegree = $this->inDegree($catalog);

        // Colonne per tabella: nome minuscolo => [nome reale, categoria tipo]; frequenza dei nomi colonna.
        $cols = [];
        $freq = [];
        foreach ($index as $full => $list) {
            foreach ($list as $c) {
                $lower = mb_strtolower($c[MetadataCache::C_NAME]);
                $cols[$full][$lower] = [$c[MetadataCache::C_NAME], Column::categoryOf($c[MetadataCache::C_TYPE])];
                $freq[$lower] = ($freq[$lower] ?? 0) + 1;
            }
        }

        // FK già dichiarate (origine + colonne): non riproporle come candidate.
        $declared = [];
        foreach ($catalog->fks() as $fk) {
            $declared[$fk['from'] . '|' . mb_strtolower(implode(',', $fk['from_cols']))] = true;
        }

        $out = [];
        $add = function (string $from, array $fromCols, string $to, array $toCols, int $score, int $evidence, string $reason) use (&$out, $cols, $declared): void {
            if ($from === $to || isset($declared[$from . '|' . mb_strtolower(implode(',', $fromCols))])) {
                return;
            }
            foreach ($fromCols as $i => $fc) { // tipi compatibili colonna per colonna
                $a = $cols[$from][mb_strtolower($fc)][1] ?? null;
                $b = $cols[$to][mb_strtolower($toCols[$i])][1] ?? null;
                if ($a === null || $a !== $b) {
                    return;
                }
            }
            $key = $from . '|' . mb_strtolower(implode(',', $fromCols)) . '|' . $to;
            if (!isset($out[$key]) || $out[$key][4] < $score) {
                $out[$key] = [$from, $to, $fromCols, $toCols, $score, $evidence, $reason];
            }
        };

        // 1) Apprese dalle FK dichiarate
        $sigs = [];
        foreach ($catalog->fks() as $fk) {
            if (count($fk['from_cols']) === 1 && ($inDegree[$fk['to']] ?? 0) > self::HUB_SKIP) {
                continue;
            }
            $key = mb_strtolower(implode(',', $fk['from_cols']));
            $target = $fk['to'] . '|' . implode(',', $fk['to_cols']);
            $sigs[$key]['cols'] ??= $fk['from_cols'];
            $sigs[$key]['targets'][$target] = ($sigs[$key]['targets'][$target] ?? 0) + 1;
            $sigs[$key]['total'] = ($sigs[$key]['total'] ?? 0) + 1;
        }
        $byColumn = []; // colonna più rara della combinazione => combinazioni
        foreach ($sigs as $key => $sig) {
            if ($sig['total'] < self::MIN_LEARNED) {
                continue;
            }
            arsort($sig['targets']);
            $best = array_key_first($sig['targets']);
            $sigs[$key]['best'] = $best;
            $sigs[$key]['evidence'] = $sig['targets'][$best];
            $sigs[$key]['score'] = (int) round($sig['targets'][$best] / $sig['total'] * 100);
            if ($sigs[$key]['score'] < self::MIN_SCORE) {
                continue;
            }
            $rarest = null;
            foreach ($sig['cols'] as $c) {
                $l = mb_strtolower($c);
                if ($rarest === null || ($freq[$l] ?? 0) < ($freq[$rarest] ?? 0)) {
                    $rarest = $l;
                }
            }
            $byColumn[$rarest][] = $key;
        }
        foreach ($cols as $full => $tableCols) {
            foreach ($tableCols as $lower => $_) {
                foreach ($byColumn[$lower] ?? [] as $key) {
                    $sig = $sigs[$key];
                    $real = [];
                    foreach ($sig['cols'] as $c) {
                        if (!isset($tableCols[mb_strtolower($c)])) {
                            continue 2;
                        }
                        $real[] = $tableCols[mb_strtolower($c)][0];
                    }
                    [$to, $toCols] = explode('|', $sig['best'], 2);
                    $add($full, $real, $to, explode(',', $toCols), $sig['score'], $sig['evidence'], 'learned');
                }
            }
        }

        // 2) Nome derivato da tabelle con PK a colonna singola
        $byName = [];
        foreach ($cols as $full => $tableCols) {
            foreach ($tableCols as $lower => $_) {
                $byName[$lower][] = $full;
            }
        }
        foreach ($catalog->objects() as $to => $o) {
            if (count($o['pk']) !== 1) {
                continue;
            }
            $pk = mb_strtolower($o['pk'][0]);
            $t = mb_strtolower($o['name']);
            foreach (array_unique(["{$t}_{$pk}", "{$pk}_{$t}", "{$t}{$pk}", "id_{$t}", "{$t}_id", "id{$t}", "{$t}id"]) as $name) {
                foreach ($byName[$name] ?? [] as $from) {
                    $add($from, [$cols[$from][$name][0]], $to, [$o['pk'][0]], 60, 0, 'name');
                }
            }
        }

        $out = array_values($out);
        usort($out, static fn (array $a, array $b) => [$b[4], $b[5]] <=> [$a[4], $a[5]]);
        return $out;
    }

    /**
     * Candidate senza quelle verso tabelle hub (se richiesto), con flag 'hub' per l'interfaccia.
     * @return list<array<string, mixed>>
     */
    public function candidatesFor(Catalog $catalog, bool $includeHubs): array
    {
        $in = $this->inDegree($catalog);
        $out = [];
        foreach ($this->candidates($catalog) as $c) {
            $c['hub'] = ($in[$c['to']] ?? 0) > self::HUB_IN;
            $c['reason_label'] = self::REASONS[$c['reason']] ?? '';
            if ($includeHubs || !$c['hub']) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /**
     * Tutti gli archi del grafo delle relazioni: FK dichiarate (kind 'fk') + candidate con punteggio minimo
     * (kind 'candidate'). Usato da percorso e grafo.
     * @return list<array<string, mixed>>
     */
    public function edges(Catalog $catalog, bool $withCandidates, int $minScore): array
    {
        // relazioni definite dall'utente: sempre incluse, prima di tutto
        $edges = array_merge($this->manual($catalog), array_map(static fn (array $fk) => $fk + ['kind' => 'fk'], $catalog->fks()));
        if ($withCandidates) {
            foreach ($this->candidatesFor($catalog, true) as $c) {
                if ($c['score'] >= $minScore) {
                    $edges[] = $c + ['kind' => 'candidate'];
                }
            }
        }
        return $edges;
    }

    /** FK entranti per tabella. @return array<string, int> */
    public function inDegree(Catalog $catalog): array
    {
        $counts = [];
        foreach ($catalog->fks() as $fk) {
            $counts[$fk['to']] = ($counts[$fk['to']] ?? 0) + 1;
        }
        return $counts;
    }
}
