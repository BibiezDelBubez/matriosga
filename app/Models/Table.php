<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\Sql;

/** Tabella o vista del Catalog. */
final class Table
{
    public readonly string $schema;
    public readonly string $name;
    public readonly bool $isView;
    public readonly ?int $rows;
    public readonly ?string $description;
    public readonly ?string $modified;

    /** @var list<Column> */
    private array $columns = [];
    /** @var array<string, Column> nome minuscolo => colonna */
    private array $byName = [];

    /**
     * @param array<string, mixed> $object
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $indexes
     */
    public function __construct(
        private readonly Catalog $catalog,
        public readonly string $fullName,
        array $object,
        array $columns,
        private readonly array $indexes,
    ) {
        $this->schema = $object['schema'];
        $this->name = $object['name'];
        $this->isView = $object['type'] === 'V';
        $this->rows = $object['rows'];
        $this->description = $object['description'];
        $this->modified = $object['modified'] ?? null;
        foreach ($columns as $c) {
            $col = new Column($this, $c);
            $this->columns[] = $col;
            $this->byName[mb_strtolower($col->name)] = $col;
        }
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function column(string $name): ?Column
    {
        return $this->byName[mb_strtolower(str_replace(['[', ']'], '', trim($name)))] ?? null;
    }

    /** Nome SQL quotato e sicuro: [schema].[tabella] */
    public function quoted(): string
    {
        return Sql::table($this->schema, $this->name);
    }

    /** @return array{name: string, columns: list<string>, type: string}|null */
    public function pk(): ?array
    {
        foreach ($this->indexes as $index) {
            if ($index['pk']) {
                return ['name' => $index['name'], 'columns' => $index['columns'], 'type' => $index['type']];
            }
        }
        return null;
    }

    /** @return list<string> */
    public function pkColumns(): array
    {
        return $this->pk()['columns'] ?? [];
    }

    /**
     * Colonne scelte per nome (ignorando nomi non validi), nell'ordine della tabella.
     * @param list<string> $names
     * @return list<Column>
     */
    public function pickColumns(array $names): array
    {
        $wanted = array_flip(array_map('mb_strtolower', $names));
        return array_values(array_filter($this->columns, static fn (Column $c) => isset($wanted[mb_strtolower($c->name)])));
    }

    /** @return list<array<string, mixed>> */
    public function indexes(): array
    {
        return $this->indexes;
    }

    /** @return list<array<string, mixed>> */
    public function fksOut(): array
    {
        return $this->catalog->fksFrom($this->fullName);
    }

    /** @return list<array<string, mixed>> */
    public function fksIn(): array
    {
        return $this->catalog->fksTo($this->fullName);
    }
}
