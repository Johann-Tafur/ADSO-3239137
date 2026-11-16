<?php

namespace App\Application\Ports;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Results\KitView;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;

/** Guarda y lee kits. El adaptador real (T-12, T-13) usa la tabla robotics.kit. */
interface KitRepository
{
    /** Id nuevo (UUID) para un kit que todavía no existe. */
    public function nextId(): string;

    /** Un kit activo; null si no existe o está dado de baja (CA-04.3). */
    public function findActive(string $id): ?Kit;

    /** El kit activo que ya usa esa referencia, si hay uno (CA-03.2). */
    public function findActiveByReference(KitReference $reference): ?Kit;

    /**
     * Busca kits activos por texto parcial en nombre o fabricante y por categoría, ordenados por nombre (CA-04.1).
     *
     * @return Page<KitView>
     */
    public function search(?string $text, ?string $categoryId, PageRequest $page): Page;

    /**
     * Guarda un kit nuevo.
     *
     * @throws \App\Domain\Errors\ReferenceTaken si otro guardó la misma referencia primero (índice único parcial)
     */
    public function add(Kit $kit): void;

    /**
     * Guarda los cambios con concurrencia optimista (ADR-002): UPDATE … WHERE id = ? AND version = ?, y version + 1.
     *
     * @throws \App\Domain\Errors\KitNotAvailable si otra petición cambió el kit primero (0 filas)
     * @throws \App\Domain\Errors\ReferenceTaken si la nueva referencia ya la tomó otro kit activo
     */
    public function save(Kit $kit): void;
}
