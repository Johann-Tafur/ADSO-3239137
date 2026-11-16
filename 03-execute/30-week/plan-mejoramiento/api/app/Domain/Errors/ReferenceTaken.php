<?php

namespace App\Domain\Errors;

/** Ya hay un kit activo con esa referencia (REFERENCE_TAKEN, 409, CA-03.2). */
final class ReferenceTaken extends DomainError
{
    public static function reference(string $reference): self
    {
        return new self(
            "Ya existe un kit activo con la referencia {$reference}.",
            [['field' => 'reference', 'issue' => 'repetida']],
        );
    }

    public function errorCode(): string
    {
        return 'REFERENCE_TAKEN';
    }
}
