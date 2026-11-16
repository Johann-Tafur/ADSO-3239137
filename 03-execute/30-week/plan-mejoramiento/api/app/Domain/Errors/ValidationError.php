<?php

namespace App\Domain\Errors;

/** Un dato de entrada no cumple su formato o su rango (VALIDATION_ERROR, 422). */
final class ValidationError extends DomainError
{
    public static function forField(string $field, string $issue, string $message): self
    {
        return new self($message, [['field' => $field, 'issue' => $issue]]);
    }

    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }
}
