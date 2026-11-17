<?php

namespace App\Domain\Errors;

/**
 * Otra petición guardó el kit entre la lectura y el guardado: la versión ya no coincide (KIT_CHANGED, 409, ADR-002).
 * Al prestar, LendKitsService lo traduce a KIT_NOT_AVAILABLE (RNF-2); en las demás escrituras llega tal cual.
 */
final class KitChanged extends DomainError
{
    public static function kit(string $kitName): self
    {
        return new self(
            "El kit «{$kitName}» cambió mientras se guardaba. Intenta de nuevo.",
            [['field' => 'kit_id', 'issue' => 'cambió']],
        );
    }

    public function errorCode(): string
    {
        return 'KIT_CHANGED';
    }
}
