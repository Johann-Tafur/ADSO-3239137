<?php

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\KitRepository;
use App\Domain\Errors\NotFound;

/**
 * HU-03 · Baja lógica de un kit (CA-03.5, ADR-003).
 * Deja de aparecer en el catálogo, pero los préstamos pasados lo siguen mostrando.
 * Con unidades prestadas → KIT_HAS_OPEN_LOANS. Un kit ya dado de baja → NOT_FOUND, como cualquier consulta (CA-04.3).
 */
final class RemoveKitService
{
    public function __construct(
        private readonly KitRepository $kits,
        private readonly Clock $clock,
    ) {}

    public function remove(string $id): void
    {
        $kit = $this->kits->findActive($id) ?? throw NotFound::resource('el kit');
        $kit->remove($this->clock->now());
        $this->kits->save($kit);
    }
}
