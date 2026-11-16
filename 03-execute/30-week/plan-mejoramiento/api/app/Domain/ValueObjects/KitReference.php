<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\ValidationError;

/** Referencia del kit: tres letras mayúsculas, guion y cuatro dígitos, por ejemplo ARD-0001 (CA-03.2). */
final readonly class KitReference
{
    private const FORMAT = '/^[A-Z]{3}-[0-9]{4}$/';

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (preg_match(self::FORMAT, $value) !== 1) {
            throw ValidationError::forField(
                'reference',
                'formato AAA-0000',
                "La referencia «{$value}» no tiene el formato AAA-0000 (tres letras mayúsculas, guion y cuatro dígitos).",
            );
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
