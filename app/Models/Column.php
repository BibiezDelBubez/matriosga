<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\Sql;

/** Colonna di una tabella/vista. Unico punto per la classificazione dei tipi SQL. */
final class Column
{
    private const UNICODE = ['nchar', 'nvarchar', 'ntext'];
    private const WITH_LENGTH = ['char', 'varchar', 'nchar', 'nvarchar', 'binary', 'varbinary'];
    private const WITH_PRECISION = ['decimal', 'numeric'];
    private const WITH_SCALE = ['datetime2', 'time', 'datetimeoffset'];

    /** Categoria logica per tipo SQL: guida ricerca valore, analisi e confronti. */
    private const CATEGORIES = [
        'string' => ['char', 'varchar', 'nchar', 'nvarchar', 'sysname'],
        'text'   => ['text', 'ntext'],
        'int'    => ['tinyint', 'smallint', 'int', 'bigint', 'bit'],
        'number' => ['decimal', 'numeric', 'money', 'smallmoney', 'float', 'real'],
        'guid'   => ['uniqueidentifier'],
        'date'   => ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'time'],
        'binary' => ['binary', 'varbinary', 'image', 'timestamp', 'rowversion'],
    ];

    /** Etichette delle categorie per filtri e badge. */
    public const CATEGORY_LABELS = [
        'string' => 'Testo',
        'text'   => 'Testo lungo (text)',
        'int'    => 'Intero',
        'number' => 'Decimale',
        'guid'   => 'GUID',
        'date'   => 'Data/ora',
        'binary' => 'Binario',
        'other'  => 'Altro',
    ];

    public readonly int $id;
    public readonly string $name;
    public readonly string $type;
    public readonly ?string $userType;
    public readonly int $maxLength;
    public readonly int $precision;
    public readonly int $scale;
    public readonly bool $nullable;
    public readonly bool $identity;
    public readonly bool $computed;
    public readonly ?string $default;
    public readonly ?string $collation;
    public readonly ?string $description;

    /** @param array<string, mixed> $d */
    public function __construct(public readonly Table $table, array $d)
    {
        $this->id = $d['id'];
        $this->name = $d['name'];
        $this->type = $d['type'];
        $this->userType = $d['user_type'];
        $this->maxLength = $d['max_length'];
        $this->precision = $d['precision'];
        $this->scale = $d['scale'];
        $this->nullable = $d['nullable'];
        $this->identity = $d['identity'];
        $this->computed = $d['computed'];
        $this->default = $d['default'];
        $this->collation = $d['collation'];
        $this->description = $d['description'];
    }

    /** Lunghezza in caratteri (-1 = max), null se il tipo non ha lunghezza. */
    public function length(): ?int
    {
        return self::lengthOf($this->type, $this->maxLength);
    }

    /** Tipo leggibile: nvarchar(50), varchar(max), decimal(18,2), datetime2(7)... */
    public function typeLabel(): string
    {
        return self::label($this->type, $this->maxLength, $this->precision, $this->scale);
    }

    public function category(): string
    {
        return self::categoryOf($this->type);
    }

    public function quoted(): string
    {
        return Sql::quoteIdent($this->name);
    }

    public function fullName(): string
    {
        return $this->table->fullName . '.' . $this->name;
    }

    public function isPk(): bool
    {
        return in_array($this->name, $this->table->pkColumns(), true);
    }

    /** @return list<array<string, mixed>> FK in uscita che includono questa colonna */
    public function fks(): array
    {
        return array_values(array_filter($this->table->fksOut(), fn (array $fk) => in_array($this->name, $fk['from_cols'], true)));
    }

    /** @return list<string> nomi degli indici (non PK) che hanno questa colonna come chiave */
    public function indexNames(): array
    {
        $names = [];
        foreach ($this->table->indexes() as $index) {
            if (!$index['pk'] && in_array($this->name, $index['columns'], true)) {
                $names[] = $index['name'];
            }
        }
        return $names;
    }

    // --- funzioni statiche riusate su dati grezzi (ricerche veloci senza creare oggetti) ---

    public static function lengthOf(string $type, int $maxLength): ?int
    {
        if (!in_array($type, self::WITH_LENGTH, true)) {
            return null;
        }
        if ($maxLength === -1) {
            return -1;
        }
        return in_array($type, self::UNICODE, true) ? intdiv($maxLength, 2) : $maxLength;
    }

    public static function label(string $type, int $maxLength, int $precision, int $scale): string
    {
        if (in_array($type, self::WITH_LENGTH, true)) {
            $len = self::lengthOf($type, $maxLength);
            return $type . '(' . ($len === -1 ? 'max' : $len) . ')';
        }
        if (in_array($type, self::WITH_PRECISION, true)) {
            return "{$type}({$precision},{$scale})";
        }
        if (in_array($type, self::WITH_SCALE, true)) {
            return "{$type}({$scale})";
        }
        return $type;
    }

    public static function categoryOf(string $type): string
    {
        foreach (self::CATEGORIES as $category => $types) {
            if (in_array($type, $types, true)) {
                return $category;
            }
        }
        return 'other';
    }
}
