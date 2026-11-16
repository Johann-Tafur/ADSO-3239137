<?php

namespace App\Domain\Errors;

/** El kit no tiene unidades disponibles o ya no está activo (KIT_NOT_AVAILABLE, 409, CA-06.3 y RNF-2). */
final class KitNotAvailable extends DomainError
{
    public static function named(string $kitName): self
    {
        return new self(
            "No quedan unidades disponibles del kit «{$kitName}».",
            [['field' => 'kit_id', 'issue' => 'sin unidades']],
        );
    }

    public function errorCode(): string
    {
        return 'KIT_NOT_AVAILABLE';
    }
}
