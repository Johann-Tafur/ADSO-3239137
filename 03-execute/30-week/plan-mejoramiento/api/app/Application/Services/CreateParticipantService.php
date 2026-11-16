<?php

namespace App\Application\Services;

use App\Application\Ports\ParticipantRepository;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Participant;
use App\Domain\ValueObjects\DocumentNumber;

/**
 * HU-05 · El admin o el mentor registran un participante (CA-05.1).
 * Documento repetido → DOCUMENT_TAKEN. Se revisa antes de guardar, y si dos lo registran a la vez,
 * el índice único uq_participant_document_number frena al segundo con el mismo error.
 */
final class CreateParticipantService
{
    public function __construct(private readonly ParticipantRepository $participants) {}

    public function create(string $fullName, string $documentNumber): Participant
    {
        // 1. Validar la entrada (el documento se valida solo; el nombre lo valida Participant::create)
        $document = new DocumentNumber($documentNumber);

        // 2. Reglas que miran la base
        if ($this->participants->findByDocument($document) !== null) {
            throw DocumentTaken::document($document->value);
        }

        // 3. Crear y guardar
        $participant = Participant::create($this->participants->nextId(), $fullName, $document);
        $this->participants->add($participant);

        return $participant;
    }
}
