<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Models\Catalog;

/**
 * Tabelle "rumore" da nascondere di default: vuote (0 righe) e copie di sicurezza.
 *
 * Una copia si riconosce SOLO dal nome, quindi si usano regole forti, verificate su SGA:
 * prefissi Save_/Xx/XX/Old_, suffissi Save/_SAVE/Old, "BeforeRepair", date nel nome (2019_2_12…).
 * "Backup" NON è una regola: su SGA esistono tabelle vere come …ConfigurazioniPathBackup.
 * Le viste non sono mai considerate rumore (non hanno un conteggio righe).
 */
final class TableFilterService
{
    private const COPY_RULES = [
        '/^(save_|xx|old_)/i'                       => 'nome che inizia con Save_/Xx/Old_',
        // maiuscole volute: "…FormsSave"/"…AttiviOld" sì, "Threshold" no
        '/(_save|_old|Save|Old|SAVE|OLD)$/'         => 'nome che finisce con Save/Old',
        '/beforerepair|beforecambio/i'              => 'nome con "Before…" (copia prima di una modifica)',
        '/_?\d{4}_\d{1,2}_\d{1,2}(_\d{1,2}){0,3}/'  => 'data nel nome (copia datata)',
        '/_save_|_\d{8}$/i'                         => 'nome con _SAVE_ o data finale',
    ];

    /** @var array<string, array<string, string>>|null full => ['empty' => '...', 'copy' => motivo] */
    private ?array $noise = null;

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * Tabelle da nascondere. $showAll = true (interruttore "mostra anche vuote e copie") → nessuna.
     * @return array<string, true> set di nomi completi
     */
    public function hidden(Catalog $catalog, bool $showAll = false): array
    {
        if ($showAll) {
            return [];
        }
        $hideEmpty = (bool) $this->settings->get('filters.hide_empty', true);
        $hideCopies = (bool) $this->settings->get('filters.hide_copies', true);
        $out = [];
        foreach ($this->noise($catalog) as $full => $why) {
            if (($hideEmpty && isset($why['empty'])) || ($hideCopies && isset($why['copy']))) {
                $out[$full] = true;
            }
        }
        return $out;
    }

    /** @return array{empty: int, copies: int, hidden: int} per i messaggi "N tabelle nascoste" */
    public function counts(Catalog $catalog): array
    {
        $noise = $this->noise($catalog);
        return [
            'empty'  => count(array_filter($noise, static fn ($w) => isset($w['empty']))),
            'copies' => count(array_filter($noise, static fn ($w) => isset($w['copy']))),
            'hidden' => count($this->hidden($catalog)),
        ];
    }

    /** Motivo per cui una tabella è considerata copia, o null. */
    public function copyReason(Catalog $catalog, string $full): ?string
    {
        return $this->noise($catalog)[$full]['copy'] ?? null;
    }

    /** @return array<string, array<string, string>> */
    private function noise(Catalog $catalog): array
    {
        if ($this->noise !== null) {
            return $this->noise;
        }
        $this->noise = [];
        foreach ($catalog->objects() as $full => $o) {
            if ($o['type'] === 'V') {
                continue;
            }
            if ($o['rows'] === 0) {
                $this->noise[$full]['empty'] = 'vuota';
            }
            foreach (self::COPY_RULES as $pattern => $reason) {
                if (preg_match($pattern, $o['name'])) {
                    $this->noise[$full]['copy'] = $reason;
                    break;
                }
            }
        }
        return $this->noise;
    }
}
