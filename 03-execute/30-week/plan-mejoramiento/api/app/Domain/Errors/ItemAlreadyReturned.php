<?php

namespace App\Domain\Errors;

/** La línea ya se había devuelto; nunca se suma dos veces (ITEM_ALREADY_RETURNED, 409, CA-07.2). */
final class ItemAlreadyReturned extends DomainError
{
    public static function item(string $itemId): self
    {
        return new self(
            'Esta línea del préstamo ya fue devuelta.',
            [['field' => 'item_id', 'issue' => "ya devuelta: {$itemId}"]],
        );
    }

    public function errorCode(): string
    {
        return 'ITEM_ALREADY_RETURNED';
    }
}
