<?php

namespace App\Domain;

use App\Domain\Errors\ValidationError;
use App\Domain\ValueObjects\DocumentNumber;

/**
 * Participante del semillero: pide kits, pero no inicia sesión (HU-05).
 * Es un dato personal (data-model.md §8). No se elimina ni se edita (CA-05.3).
 * Que el documento no se repita lo garantiza el índice único; el servicio responde DOCUMENT_TAKEN (T-08).
 */
final class Participant
{
    private const FULL_NAME_MAX = 200;

    private function __construct(
        private readonly string $id,
        private readonly string $fullName,
        private readonly DocumentNumber $documentNumber,
    ) {}

    /** Alta de un participante nuevo: el nombre se guarda sin espacios a los lados (CA-05.1). */
    public static function create(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        $fullName = trim($fullName);
        if ($fullName === '' || mb_strlen($fullName) > self::FULL_NAME_MAX) {
            throw ValidationError::forField(
                'full_name',
                'entre 1 y '.self::FULL_NAME_MAX.' caracteres',
                'El nombre del participante es obligatorio y puede tener máximo '.self::FULL_NAME_MAX.' caracteres.',
            );
        }

        return new self($id, $fullName, $documentNumber);
    }

    /** Reconstruye un participante leído de la base. Lo usa solo el repositorio. */
    public static function restore(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        return new self($id, $fullName, $documentNumber);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): DocumentNumber
    {
        return $this->documentNumber;
    }
}
