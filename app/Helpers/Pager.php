<?php
declare(strict_types=1);

namespace App\Helpers;

/** Paginazione: calcoli in un solo posto; la resa è in partials/pager.php. */
final class Pager
{
    public readonly int $page;
    public readonly int $pages;

    public function __construct(int $page, public readonly int $perPage, public readonly int $total)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = max(1, min($page, $this->pages));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** Numero (1-based) del primo e dell'ultimo elemento della pagina. @return array{0: int, 1: int} */
    public function range(): array
    {
        return $this->total === 0 ? [0, 0] : [$this->offset() + 1, min($this->total, $this->offset() + $this->perPage)];
    }

    /** @template T @param list<T> $items @return list<T> */
    public function slice(array $items): array
    {
        return array_slice($items, $this->offset(), $this->perPage);
    }
}
