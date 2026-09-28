<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\Catalog;

/**
 * Percorsi tra due tabelle sul grafo delle relazioni (archi non orientati).
 *  - archi: FK dichiarate, opzionalmente relazioni candidate (punteggio minimo)
 *  - le tabelle hub (es. BaSocieta, BaCliFor) non possono essere passaggi intermedi, salvo richiesta:
 *    altrimenti quasi ogni coppia sarebbe collegata tramite "A → BaSocieta ← B", che non serve a nulla
 *  - ricerca: BFS dalla destinazione (distanze) + DFS potata dalla partenza, lunghezza ≤ min(max, minima+1)
 */
final class PathFinderService
{
    private const MAX_EXPANSIONS = 200000; // freno di sicurezza contro esplosioni combinatorie

    /** Fra due tabelle vince la relazione più affidabile: definita dall'utente > FK dichiarata > candidata. */
    private const KIND_RANK = ['manual' => 3, 'fk' => 2, 'candidate' => 1];

    /** @var array<string, array> grafi già costruiti in questa richiesta (connect() chiama find() più volte) */
    private array $graphs = [];

    public function __construct(private readonly RelationshipService $relations)
    {
    }

    /**
     * @param array{depth: int, max: int, candidates: bool, minScore: int, hubs: bool, avoid: list<string>, hidden?: array<string, true>} $opt
     * @return array{paths: list<array>, distance: ?int, truncated: bool, blocked: list<string>}
     */
    public function find(Catalog $catalog, string $from, string $to, array $opt): array
    {
        if ($from === $to) {
            throw HttpException::badRequest('Partenza e destinazione sono la stessa tabella.');
        }
        [$adj, $inDegree] = $this->graph($catalog, $opt['candidates'], $opt['minScore']);
        // tabelle da non attraversare: scelte dall'utente + vuote/copie (TableFilterService)
        $avoid = array_flip($opt['avoid']) + ($opt['hidden'] ?? []);
        $allowed = static fn (string $n): bool => $n === $from || $n === $to
            || (!isset($avoid[$n]) && ($opt['hubs'] || ($inDegree[$n] ?? 0) <= RelationshipService::HUB_IN));

        // Distanze dalla destinazione (solo attraverso nodi ammessi come intermedi).
        $dist = [$to => 0];
        $queue = [$to];
        for ($i = 0; $i < count($queue); $i++) {
            $u = $queue[$i];
            if ($u !== $to && !$allowed($u)) {
                continue;
            }
            foreach ($adj[$u] ?? [] as $v => $_) {
                if (!isset($dist[$v]) && ($allowed($v) || $v === $from)) {
                    $dist[$v] = $dist[$u] + 1;
                    $queue[] = $v;
                }
            }
        }
        $blocked = $this->blockedHubs($adj, $from, $to, $inDegree, $opt['hubs']);
        if (!isset($dist[$from]) || $dist[$from] > $opt['depth']) {
            return ['paths' => [], 'distance' => $dist[$from] ?? null, 'truncated' => false, 'blocked' => $blocked];
        }

        // Tutti i percorsi semplici fino a limit passi (potatura con le distanze).
        $limit = min($opt['depth'], $dist[$from] + 1);
        $found = [];
        $expansions = 0;
        $truncated = false;
        $path = [$from];
        $visited = [$from => true];
        $walk = function (string $u) use (&$walk, &$found, &$expansions, &$truncated, &$path, &$visited, $adj, $dist, $to, $limit, $allowed): void {
            if ($u === $to) {
                $found[] = $path;
                return;
            }
            if ($u !== $path[0] && !$allowed($u)) {
                return;
            }
            $depth = count($path) - 1;
            foreach ($adj[$u] ?? [] as $v => $_) {
                if (isset($visited[$v]) || !isset($dist[$v]) || $depth + 1 + $dist[$v] > $limit) {
                    continue;
                }
                if (++$expansions > self::MAX_EXPANSIONS || count($found) >= 500) {
                    $truncated = true;
                    return;
                }
                $path[] = $v;
                $visited[$v] = true;
                $walk($v);
                array_pop($path);
                unset($visited[$v]);
            }
        };
        $walk($from);

        $paths = array_map(fn (array $nodes) => $this->describe($nodes, $adj, $inDegree), $found);
        usort($paths, [self::class, 'compare']);

        return [
            'paths'     => array_slice($paths, 0, $opt['max']),
            'distance'  => $dist[$from],
            'truncated' => $truncated || count($paths) > $opt['max'],
            'blocked'   => $blocked,
        ];
    }

    /**
     * Come collegare una tabella nuova a un gruppo di tabelle già scelte (costruttore di query).
     * Percorsi dalla tabella nuova a ciascuna tabella del gruppo, tagliati alla prima tabella del gruppo
     * incontrata e girati: ogni percorso parte da una tabella del gruppo e arriva alla nuova.
     * @param list<string> $existing
     * @return list<array> percorsi nel formato di find() (più corti prima)
     */
    public function connect(Catalog $catalog, string $new, array $existing, array $opt): array
    {
        $inGroup = array_flip($existing);
        $all = [];
        foreach ($existing as $target) {
            foreach ($this->find($catalog, $new, $target, ['max' => 5] + $opt)['paths'] as $path) {
                // taglio alla prima tabella del gruppo (il percorso può attraversarne un'altra prima di $target)
                $steps = [];
                foreach ($path['steps'] as $s) {
                    $steps[] = $s;
                    if (isset($inGroup[$s['b']])) {
                        break;
                    }
                }
                $reversed = array_map(static fn (array $s) => [
                    'a' => $s['b'], 'b' => $s['a'], 'rel' => $s['rel'], 'forward' => !$s['forward'],
                    'cols_a' => $s['cols_b'], 'cols_b' => $s['cols_a'],
                ], array_reverse($steps));
                $nodes = array_merge([$reversed[0]['a']], array_column($reversed, 'b'));
                $all[implode('>', $nodes)] = self::summary($nodes, $reversed, $path['generic']);
            }
        }
        $all = array_values($all);
        usort($all, [self::class, 'compare']);
        return array_slice($all, 0, $opt['max']);
    }

    /**
     * Grafo non orientato: nodo => [vicino => relazione migliore]. Una FK dichiarata batte sempre
     * una candidata; fra candidate vince il punteggio più alto.
     * @return array{0: array<string, array<string, array>>, 1: array<string, int>}
     */
    private function graph(Catalog $catalog, bool $withCandidates, int $minScore): array
    {
        $key = ($withCandidates ? 'c' : 'f') . $minScore;
        return $this->graphs[$key] ??= $this->buildGraph($catalog, $withCandidates, $minScore);
    }

    /** @return array{0: array<string, array<string, array>>, 1: array<string, int>} */
    private function buildGraph(Catalog $catalog, bool $withCandidates, int $minScore): array
    {
        $adj = [];
        foreach ($this->relations->edges($catalog, $withCandidates, $minScore) as $rel) {
            [$a, $b] = [$rel['from'], $rel['to']];
            if ($a === $b) {
                continue;
            }
            $current = $adj[$a][$b] ?? null;
            $better = $current === null
                || self::KIND_RANK[$rel['kind']] > self::KIND_RANK[$current['kind']]
                || ($current['kind'] === $rel['kind'] && $rel['kind'] === 'candidate' && $rel['score'] > $current['score']);
            if ($better) {
                $adj[$a][$b] = $adj[$b][$a] = $rel;
            }
        }
        return [$adj, $this->relations->inDegree($catalog)];
    }

    /** @param list<string> $nodes */
    private function describe(array $nodes, array $adj, array $inDegree): array
    {
        $steps = [];
        $candidates = 0;
        for ($i = 0; $i < count($nodes) - 1; $i++) {
            $rel = $adj[$nodes[$i]][$nodes[$i + 1]];
            $candidates += $rel['kind'] === 'candidate' ? 1 : 0;
            // forward: la tabella "a" è quella che punta (contiene le colonne "from" della relazione)
            $forward = $rel['from'] === $nodes[$i];
            $steps[] = [
                'a'       => $nodes[$i],
                'b'       => $nodes[$i + 1],
                'rel'     => $rel,
                'forward' => $forward,
                'cols_a'  => $forward ? $rel['from_cols'] : $rel['to_cols'],
                'cols_b'  => $forward ? $rel['to_cols'] : $rel['from_cols'],
            ];
        }
        $generic = 0;
        foreach (array_slice($nodes, 1, -1) as $n) {
            $generic += $inDegree[$n] ?? 0;
        }
        return self::summary($nodes, $steps, $generic);
    }

    /**
     * Dati di confronto di un percorso.
     * weak = passaggi "A → X ← B": A e B puntano entrambe alla stessa tabella X (tipicamente un'anagrafica
     *        o una tabella di configurazione). Collegano righe che hanno solo un valore in comune: la JOIN
     *        moltiplica le righe e quasi mai è quello che serve.
     * @param list<string> $nodes
     * @param list<array> $steps
     * @return array{nodes: list<string>, steps: list<array>, candidates: int, manual: int, weak: int, generic: int}
     */
    private static function summary(array $nodes, array $steps, int $generic): array
    {
        $weak = 0;
        for ($i = 0; $i < count($steps) - 1; $i++) {
            if ($steps[$i]['forward'] && !$steps[$i + 1]['forward']) {
                $weak++;
            }
        }
        $count = static fn (string $kind) => count(array_filter($steps, static fn (array $s) => $s['rel']['kind'] === $kind));
        return ['nodes' => $nodes, 'steps' => $steps, 'candidates' => $count('candidate'), 'manual' => $count('manual'), 'weak' => $weak, 'generic' => $generic];
    }

    /**
     * Ordine dei percorsi: prima quelli senza passaggi deboli, poi i più corti, poi quelli che usano
     * relazioni definite dall'utente, poi meno candidate, poi tabelle intermedie meno "generiche".
     */
    private static function compare(array $a, array $b): int
    {
        return [$a['weak'] > 0, count($a['steps']), -$a['manual'], $a['candidates'], $a['generic']]
            <=> [$b['weak'] > 0, count($b['steps']), -$b['manual'], $b['candidates'], $b['generic']];
    }

    /** Hub collegati direttamente a partenza o arrivo ma esclusi come passaggi: per spiegare "nessun percorso". @return list<string> */
    private function blockedHubs(array $adj, string $from, string $to, array $inDegree, bool $hubs): array
    {
        if ($hubs) {
            return [];
        }
        $out = [];
        foreach ([$from, $to] as $end) {
            foreach ($adj[$end] ?? [] as $n => $_) {
                if (($inDegree[$n] ?? 0) > RelationshipService::HUB_IN && $n !== $from && $n !== $to) {
                    $out[$n] = true;
                }
            }
        }
        return array_keys($out);
    }
}
