<?php

namespace App\Application\Ports;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Domain\Participant;
use App\Domain\ValueObjects\DocumentNumber;

/** Guarda y lee participantes. El adaptador real (T-12) usa la tabla robotics.participant. No hay borrar (CA-05.3). */
interface ParticipantRepository
{
    /** Id nuevo (UUID) para un participante que todavía no existe. */
    public function nextId(): string;

    public function find(string $id): ?Participant;

    /**
     * Lee el participante y bloquea su fila hasta que termine la transacción (SELECT … FOR UPDATE, ADR-004).
     * Así dos préstamos simultáneos para la misma persona se atienden uno detrás de otro y el límite de 2 se cuenta bien.
     * Solo tiene sentido dentro de TransactionRunner::run().
     */
    public function lockForLoan(string $id): ?Participant;

    public function findByDocument(DocumentNumber $documentNumber): ?Participant;

    /**
     * Texto parcial en el nombre o en el documento, sin distinguir mayúsculas; orden por nombre (CA-05.2).
     *
     * @return Page<Participant>
     */
    public function search(?string $text, PageRequest $page): Page;

    /**
     * Guarda un participante nuevo.
     *
     * @throws \App\Domain\Errors\DocumentTaken si otro guardó el mismo documento primero (índice único)
     */
    public function add(Participant $participant): void;
}
