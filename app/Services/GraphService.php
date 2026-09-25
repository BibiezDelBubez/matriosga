<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Catalog;

/**
 * Elementi Cytoscape per il grafo delle relazioni attorno a una tabella, entro N livelli.
 * Mai l'intero database: si parte da una tabella e ci si ferma a graph_max_nodes.
 * Le tabelle hub (BaSocieta…) compaiono solo se richiesto e non vengono espanse.
 */
final class GraphService
{
    public function __construct(
        private readonly RelationshipService $relations,
        private readonly QueryTextService $queryText,
    ) {
    }

    /**
     * @param list<string> $extra nodi da includere comunque (es. tabelle già presenti nel grafo del browser)
     * @return array{nodes: list<array>, edges: list<array>, truncated: bool, hidden_hubs: int}
     */
    public function neighborhood(Catalog $catalog, string $center, int $depth, bool $candidates, bool $hubs, int $maxNodes, array $extra = []): array
    {
        $edges = $this->relations->edges($catalog, $candidates, 80);
        $inDegree = $this->relations->inDegree($catalog);
        $isHub = static fn (string $t) => ($inDegree[$t] ?? 0) > RelationshipService::HUB_IN;

        $adj = [];
        foreach ($edges as $i => $e) {
            if ($e['from'] !== $e['to']) {
                $adj[$e['from']][$e['to']] = true;
                $adj[$e['to']][$e['from']] = true;
            }
        }

        // BFS per livelli dal centro
        $level = [$center => 0];
        $frontier = [$center];
        $truncated = false;
        $hiddenHubs = [];
        for ($d = 1; $d <= $depth && $frontier && !$truncated; $d++) {
            $next = [];
            foreach ($frontier as $u) {
                if ($u !== $center && $isHub($u)) {
                    continue; // gli hub non si espandono: porterebbero mezzo database
                }
                foreach (array_keys($adj[$u] ?? []) as $v) {
                    if (isset($level[$v])) {
                        continue;
                    }
                    if (!$hubs && $isHub($v)) {
                        $hiddenHubs[$v] = true;
                        continue;
                    }
                    if (count($level) >= $maxNodes) {
                        $truncated = true;
                        break 2;
                    }
                    $level[$v] = $d;
                    $next[] = $v;
                }
            }
            $frontier = $next;
        }
        foreach ($extra as $t) {
            if (isset($catalog->objects()[$t]) && !isset($level[$t])) {
                $level[$t] = $depth + 1;
            }
        }

        $objects = $catalog->objects();
        $nodes = [];
        foreach ($level as $t => $lv) {
            $o = $objects[$t];
            $nodes[] = ['data' => [
                'id'     => $t,
                'label'  => $o['name'],
                'schema' => $o['schema'],
                'rows'   => $o['rows'],
                'ncols'  => $o['ncols'],
                'pk'     => $o['pk'],
                'view'   => $o['type'] === 'V',
                'hub'    => $isHub($t),
                'refs'   => $inDegree[$t] ?? 0,
                'level'  => $lv,
                'center' => $t === $center,
            ]];
        }
        // Un arco per coppia (origine, destinazione, tipo): più FK fra le stesse tabelle diventano un arco "×N".
        $groups = [];
        foreach ($edges as $e) {
            if (isset($level[$e['from']], $level[$e['to']]) && $e['from'] !== $e['to']) {
                $groups[$e['from'] . '|' . $e['to'] . '|' . $e['kind']][] = $e;
            }
        }
        $out = [];
        foreach ($groups as $key => $rels) {
            $first = $rels[0];
            $out[] = ['data' => [
                'id'     => 'e' . md5($key),
                'source' => $first['from'],
                'target' => $first['to'],
                'kind'   => $first['kind'],
                'count'  => count($rels),
                'label'  => count($rels) > 1 ? '×' . count($rels) : '',
                'rels'   => array_map(fn (array $e) => [
                    'from_cols' => $e['from_cols'],
                    'to_cols'   => $e['to_cols'],
                    'name'      => $e['name'] ?? null,
                    'score'     => $e['score'] ?? null,
                    'join'      => $this->queryText->join($catalog, $e),
                ], $rels),
            ]];
        }
        return ['nodes' => $nodes, 'edges' => $out, 'truncated' => $truncated, 'hidden_hubs' => count($hiddenHubs)];
    }
}
