<?php

namespace App\Application\Services;

use App\Application\Ports\CategoryRepository;
use App\Application\Ports\KitRepository;
use App\Application\Results\KitView;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Errors\ValidationError;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;

/**
 * HU-03 · El admin crea un kit (CA-03.1, CA-03.2).
 * Las disponibles arrancan iguales al total y la versión en 1: lo decide Kit::create().
 */
final class CreateKitService
{
    public function __construct(
        private readonly KitRepository $kits,
        private readonly CategoryRepository $categories,
    ) {}

    public function create(string $name, string $manufacturer, string $reference, string $categoryId, int $totalUnits): KitView
    {
        // 1. Validar la entrada (los objetos de valor se validan solos)
        $reference = new KitReference($reference);
        $units = new Units($totalUnits);
        $category = $this->categories->find($categoryId)
            ?? throw ValidationError::forField('category_id', 'no existe', 'La categoría no existe.');

        // 2. Reglas que miran la base
        if ($this->kits->findActiveByReference($reference) !== null) {
            throw ReferenceTaken::reference($reference->value);
        }

        // 3. Crear y guardar
        $kit = Kit::create($this->kits->nextId(), $name, $manufacturer, $reference, $category->id, $units);
        $this->kits->add($kit);

        return new KitView($kit, $category);
    }
}
