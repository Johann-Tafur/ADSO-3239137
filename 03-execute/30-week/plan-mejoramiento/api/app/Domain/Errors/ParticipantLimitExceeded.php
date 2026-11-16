<?php

namespace App\Domain\Errors;

/**
 * El participante quedaría con más de 2 kits sin devolver (PARTICIPANT_LIMIT_EXCEEDED, 422, CA-06.4, RN-2).
 * El conteo actual va en el mensaje y en details, como pide api-contract.md §2.
 */
final class ParticipantLimitExceeded extends DomainError
{
    public static function withOpen(int $openItems, int $limit): self
    {
        return new self(
            "El participante ya tiene {$openItems} kit(s) sin devolver; el máximo es {$limit}.",
            [['field' => 'items', 'issue' => "sin devolver: {$openItems}"]],
        );
    }

    public function errorCode(): string
    {
        return 'PARTICIPANT_LIMIT_EXCEEDED';
    }
}
