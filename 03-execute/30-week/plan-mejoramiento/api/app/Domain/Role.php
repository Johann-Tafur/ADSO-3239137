<?php

namespace App\Domain;

use App\Domain\Errors\ValidationError;

/** Rol de quien inicia sesión (spec.md §2). Solo existen estos dos. */
enum Role: string
{
    case Admin = 'admin';
    case Mentor = 'mentor';

    /** Convierte el texto recibido en un rol; cualquier otro valor es un error de validación. */
    public static function fromName(string $value): self
    {
        return self::tryFrom(trim($value)) ?? throw ValidationError::forField(
            'role',
            'admin o mentor',
            'El rol debe ser admin o mentor.',
        );
    }
}
