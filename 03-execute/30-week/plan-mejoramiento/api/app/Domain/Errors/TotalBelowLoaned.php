<?php

namespace App\Domain\Errors;

/** El nuevo total dejaría disponibles por debajo de 0 (TOTAL_BELOW_LOANED, 422, CA-03.3). */
final class TotalBelowLoaned extends DomainError
{
    public static function because(int $newTotal, int $loaned): self
    {
        return new self(
            "No se puede dejar el total en {$newTotal}: hay {$loaned} unidades prestadas.",
            [['field' => 'total_units', 'issue' => "prestadas: {$loaned}"]],
        );
    }

    public function errorCode(): string
    {
        return 'TOTAL_BELOW_LOANED';
    }
}
