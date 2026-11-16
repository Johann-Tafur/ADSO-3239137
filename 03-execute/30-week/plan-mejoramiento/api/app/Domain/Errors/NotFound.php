<?php

namespace App\Domain\Errors;

/** El recurso no existe o está dado de baja (NOT_FOUND, 404, CA-04.3). */
final class NotFound extends DomainError
{
    public static function resource(string $label): self
    {
        return new self("No se encontró {$label}.");
    }

    public function errorCode(): string
    {
        return 'NOT_FOUND';
    }
}
