<?php

namespace App\Domain\Errors;

/** La fecha final es anterior a la inicial (INVALID_DATE_RANGE, 422, CA-10.2). */
final class InvalidDateRange extends DomainError
{
    public static function endBeforeStart(): self
    {
        return new self(
            'La fecha final no puede ser anterior a la fecha inicial.',
            [['field' => 'to', 'issue' => 'anterior a from']],
        );
    }

    public function errorCode(): string
    {
        return 'INVALID_DATE_RANGE';
    }
}
