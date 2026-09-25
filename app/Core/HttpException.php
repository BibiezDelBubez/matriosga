<?php
declare(strict_types=1);

namespace App\Core;

/** Errore "atteso" da mostrare all'utente con un codice HTTP (400, 404, ...). */
final class HttpException extends \RuntimeException
{
    public function __construct(int $status, string $message)
    {
        parent::__construct($message, $status);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function notFound(string $message = 'Elemento non trovato'): self
    {
        return new self(404, $message);
    }
}
