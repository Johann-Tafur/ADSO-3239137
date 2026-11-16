<?php

namespace App\Domain\Errors;

/**
 * Error de una regla del negocio. Lleva el `code` del contrato (api-contract.md §2) y un mensaje en español.
 * El dominio no sabe de HTTP: el estado (409, 422...) lo decide el adaptador HTTP a partir del `code`.
 */
abstract class DomainError extends \DomainException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        string $message,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** Código estable en inglés, por ejemplo VALIDATION_ERROR. */
    abstract public function errorCode(): string;

    /** @return list<array{field: string, issue: string}> */
    public function details(): array
    {
        return $this->details;
    }
}
