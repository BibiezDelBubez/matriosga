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

        // Ordine: meno passaggi, meno relazioni candidate, tabelle intermedie meno "generiche".
        $paths = array_map(fn (array $nodes) => $this->describe($nodes, $adj, $inDegree), $found);
        usort($paths, static fn (array $a, array $b) => [count($a['steps']), $a['candidates'], $a['generic']] <=> [count($b['steps']), $b['candidates'], $b['generic']]);

        return [
            'paths'     => array_slice($paths, 0, $opt['max']),
            'distance'  => $dist[$from],
            'truncated' => $truncated || count($paths) > $opt['max'],
            'blocked'   => $blocked,
        ];
    }

    /**
     * Grafo non orientato: nodo => [vicino => relazione migliore]. Una FK dichiarata batte sempre
     * una candidata; fra candidate vince il punteggio più alto.
     * @return array{0: array<string, array<string, array>>, 1: array<string, int>}
     */
    private function graph(Catalog $catalog, bool $withCandidates, int $minScore): array
    {
        $adj = [];
        foreach ($this->relations->edges($catalog, $withCandidates, $minScore) as $rel) {
            [$a, $b] = [$rel['from'], $rel['to']];
            if ($a === $b) {
                continue;
            }
            $current = $adj[$a][$b] ?? null;
            $better = $current === null
                || ($current['kind'] === 'candidate' && $rel['kind'] === 'fk')
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
        return ['nodes' => $nodes, 'steps' => $steps, 'candidates' => $candidates, 'generic' => $generic];
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
