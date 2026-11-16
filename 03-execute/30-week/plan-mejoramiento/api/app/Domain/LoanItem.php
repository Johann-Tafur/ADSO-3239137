<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Una línea del préstamo: un kit, su vencimiento y su devolución.
 * Congela nombre, referencia y categoría del kit en el instante del préstamo (CA-06.6, RN-4, artículo VIII).
 *
 * Es inmutable: nadie puede cambiarla por fuera. Para devolverla, Loan::returnItem() la reemplaza
 * por una copia con returned_at, y así la unidad del kit siempre se devuelve en el mismo paso.
 */
final readonly class LoanItem
{
    public function __construct(
        public string $id,
        public string $kitId,
        public string $kitName,
        public string $kitReference,
        public string $categoryName,
        public DateTimeImmutable $dueAt,
        public ?DateTimeImmutable $returnedAt,
    ) {}

    /** Copia de esta línea con la devolución puesta. */
    public function withReturnedAt(DateTimeImmutable $instant): self
    {
        return new self(
            $this->id,
            $this->kitId,
            $this->kitName,
            $this->kitReference,
            $this->categoryName,
            $this->dueAt,
            $instant,
        );
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    /** Sin devolver y con el vencimiento ya pasado (CA-09.1). */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    /** Días completos de retraso; 0 si no está vencida (CA-09.1). */
    public function daysOverdue(DateTimeImmutable $now): int
    {
        if (! $this->isOverdue($now)) {
            return 0;
        }

        return intdiv($now->getTimestamp() - $this->dueAt->getTimestamp(), 86400);
    }
}
