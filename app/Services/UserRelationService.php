<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Settings;
use App\Models\Catalog;
use RuntimeException;

/**
 * Relazioni definite dall'utente ("questa tabella si collega a quella con queste colonne"), per i
 * collegamenti che il database non dichiara come FK e che le candidate non trovano.
 * Salvate in storage/relations.json, separate per connessione (server+database).
 * Sono usate da Percorso, Grafo, Costruttore e dettaglio tabella con kind 'manual' (priorità sulle FK).
 */
final class UserRelationService
{
    private const FILE = BASE_PATH . '/storage/relations.json';

    public function __construct(private readonly Settings $settings)
    {
    }

    /** @return list<array{id: string, from: string, from_cols: list<string>, to: string, to_cols: list<string>, note: string, created: string}> */
    public function all(): array
    {
        return $this->read()[$this->settings->connectionId()] ?? [];
    }

    /**
     * @param list<string> $fromCols
     * @param list<string> $toCols
     * @return array<string, mixed> la relazione salvata
     */
    public function add(Catalog $catalog, string $from, array $fromCols, string $to, array $toCols, string $note): array
    {
        $a = $catalog->require($from);
        $b = $catalog->require($to);
        if ($a->fullName === $b->fullName) {
            throw HttpException::badRequest('Scegli due tabelle diverse.');
        }
        if (!$fromCols || count($fromCols) !== count($toCols)) {
            throw HttpException::badRequest('Indica almeno una coppia di colonne, complete da entrambi i lati.');
        }
        $colsA = [];
        $colsB = [];
        foreach ($fromCols as $i => $name) {
            $colsA[] = ($a->column((string) $name) ?? throw HttpException::badRequest("Colonna non trovata: {$a->fullName}.{$name}"))->name;
            $colsB[] = ($b->column((string) $toCols[$i]) ?? throw HttpException::badRequest("Colonna non trovata: {$b->fullName}.{$toCols[$i]}"))->name;
        }
        $rel = [
            'id'        => substr(md5($a->fullName . implode(',', $colsA) . $b->fullName . implode(',', $colsB)), 0, 12),
            'from'      => $a->fullName,
            'from_cols' => $colsA,
            'to'        => $b->fullName,
            'to_cols'   => $colsB,
            'note'      => mb_substr(trim($note), 0, 200),
            'created'   => date('Y-m-d H:i'),
        ];
        $data = $this->read();
        $list = array_values(array_filter($data[$this->settings->connectionId()] ?? [], static fn (array $r) => $r['id'] !== $rel['id']));
        $list[] = $rel;
        $data[$this->settings->connectionId()] = $list;
        $this->write($data);
        return $rel;
    }

    public function delete(string $id): void
    {
        $data = $this->read();
        $data[$this->settings->connectionId()] = array_values(array_filter($data[$this->settings->connectionId()] ?? [], static fn (array $r) => $r['id'] !== $id));
        $this->write($data);
    }

    /** @return array<string, list<array>> */
    private function read(): array
    {
        $data = is_file(self::FILE) ? json_decode((string) file_get_contents(self::FILE), true) : null;
        return is_array($data) ? $data : [];
    }

    private function write(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents(self::FILE, (string) $json, LOCK_EX) === false) {
            throw new RuntimeException('Impossibile scrivere storage/relations.json.');
        }
    }
}
