<?php

namespace App\Domain\Errors;

/** Ya hay un participante con ese documento (DOCUMENT_TAKEN, 409, CA-05.1). */
final class DocumentTaken extends DomainError
{
    public static function document(string $documentNumber): self
    {
        return new self(
            "Ya existe un participante con el documento {$documentNumber}.",
            [['field' => 'document_number', 'issue' => 'repetido']],
        );
    }

    public function errorCode(): string
    {
        return 'DOCUMENT_TAKEN';
    }
}
