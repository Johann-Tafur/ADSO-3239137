<?php

namespace App\Domain\Errors;

/** Un kit con unidades prestadas sin devolver no se da de baja (KIT_HAS_OPEN_LOANS, 409, CA-03.5). */
final class KitHasOpenLoans extends DomainError
{
    public static function withLoaned(int $loaned): self
    {
        return new self(
            "No se puede dar de baja el kit: tiene {$loaned} unidades prestadas sin devolver.",
            [['field' => 'id', 'issue' => "prestadas: {$loaned}"]],
        );
    }

    public function errorCode(): string
    {
        return 'KIT_HAS_OPEN_LOANS';
    }
}
