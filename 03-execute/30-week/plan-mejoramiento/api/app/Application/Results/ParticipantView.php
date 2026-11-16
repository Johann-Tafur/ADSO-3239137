<?php

namespace App\Application\Results;

use App\Domain\Participant;

/** Detalle de un participante con sus líneas sin devolver (`active_items`, la misma cuenta de RN-2). */
final readonly class ParticipantView
{
    public function __construct(
        public Participant $participant,
        public int $activeItems,
    ) {}
}
