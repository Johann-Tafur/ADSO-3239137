<?php

namespace App\Application\Services;

use App\Application\Ports\CategoryRepository;
use App\Application\Ports\KitRepository;
use App\Application\Results\KitView;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Errors\ValidationError;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;

/**
 * HU-03 · El admin reemplaza nombre, fabricante, referencia, categoría y total (PUT /kits/{id}).
 *
 * - Cambiar el total mueve las disponibles en la misma diferencia; por debajo de lo prestado → TOTAL_BELOW_LOANED (CA-03.3).
 * - Los préstamos pasados no cambian: guardan su copia del nombre y la referencia (RN-4).
 * - Si alguien prestó o devolvió mientras tanto, save() lo detecta por la versión (ADR-002).
 */
final class UpdateKitService
{
    public function __construct(
        private readonly KitRepository $kits,
        private readonly CategoryRepository $categories,
    ) {}

    public function update(
        string $id,
        string $name,
        string $manufacturer,
        string $reference,
        string $categoryId,
        int $totalUnits,
    ): KitView {
        // 1. Validar la entrada
        $reference = new KitReference($reference);
        $units = new Units($totalUnits);
        $category = $this->categories->find($categoryId)
            ?? throw ValidationError::forField('category_id', 'no existe', 'La categoría no existe.');

        // 2. Reglas que miran la base
        $kit = $this->kits->findActive($id) ?? throw NotFound::resource('el kit');
        $owner = $this->kits->findActiveByReference($reference);
        if ($owner !== null && $owner->id() !== $kit->id()) {
            throw ReferenceTaken::reference($reference->value);
        }

        // 3. Cambiar y guardar. Si changeTotal() falla, el kit cambiado se descarta sin guardarse.
        $kit->updateDetails($name, $manufacturer, $reference, $category->id);
        $kit->changeTotal($units);
        $this->kits->save($kit);

        return new KitView($kit, $category);
    }
}
