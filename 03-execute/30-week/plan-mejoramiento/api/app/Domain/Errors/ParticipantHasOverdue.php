<?php

namespace App\Domain\Errors;

/** El participante tiene algún kit vencido y no puede pedir otro (PARTICIPANT_HAS_OVERDUE, 409, CA-06.5, RN-3). */
final class ParticipantHasOverdue extends DomainError
{
    public static function withOverdue(int $overdueItems): self
    {
        return new self(
            "El participante tiene {$overdueItems} kit(s) vencido(s); debe devolverlos antes de pedir otro.",
            [['field' => 'participant_id', 'issue' => "vencidos: {$overdueItems}"]],
        );
    }

    public function errorCode(): string
    {
        return 'PARTICIPANT_HAS_OVERDUE';
    }
}
