<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\ValidationError;

/** Total de unidades de un kit: un entero mayor o igual a 1 (CA-03.3). */
final readonly class Units
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value < 1) {
            throw ValidationError::forField(
                'total_units',
                'mínimo 1',
                'El total de unidades debe ser al menos 1.',
            );
        }
        $this->value = $value;
    }
}
