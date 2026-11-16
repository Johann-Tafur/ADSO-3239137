<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\ValidationError;

/** Documento de identidad del participante: sin espacios a los lados, de 1 a 30 caracteres (data-model.md §3). */
final readonly class DocumentNumber
{
    private const MAX_LENGTH = 30;

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw ValidationError::forField(
                'document_number',
                'entre 1 y 30 caracteres',
                'El documento es obligatorio y puede tener máximo 30 caracteres.',
            );
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
