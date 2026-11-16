<?php

namespace App\Domain\Errors;

/** El mismo kit aparece dos veces en un préstamo (DUPLICATE_KIT_IN_LOAN, 422, CA-06.2). */
final class DuplicateKitInLoan extends DomainError
{
    public static function kit(string $kitId): self
    {
        return new self(
            'Un préstamo no puede llevar el mismo kit dos veces.',
            [['field' => 'items', 'issue' => "kit repetido: {$kitId}"]],
        );
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_KIT_IN_LOAN';
    }
}
