<?php

namespace App\Domain;

use App\Domain\Errors\KitHasOpenLoans;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Errors\TotalBelowLoaned;
use App\Domain\Errors\ValidationError;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use DateTimeImmutable;
use LogicException;

/**
 * Kit del catálogo (HU-03, RN-1, ADR-002, ADR-003).
 *
 * Invariante: 0 <= availableUnits <= totalUnits, siempre.
 * Unidades prestadas = totalUnits - availableUnits (cada línea abierta retiene una unidad).
 */
final class Kit
{
    private const NAME_MAX = 200;

    private const MANUFACTURER_MAX = 150;

    private function __construct(
        private readonly string $id,
        private string $name,
        private string $manufacturer,
        private KitReference $reference,
        private string $categoryId,
        private int $totalUnits,
        private int $availableUnits,
        private ?string $photoKey,
        private readonly int $version,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /** Alta de un kit nuevo: las disponibles arrancan iguales al total (CA-03.1) y la versión en 1 (ADR-002). */
    public static function create(
        string $id,
        string $name,
        string $manufacturer,
        KitReference $reference,
        string $categoryId,
        Units $totalUnits,
    ): self {
        return new self(
            $id,
            self::cleanName($name),
            self::cleanManufacturer($manufacturer),
            $reference,
            $categoryId,
            $totalUnits->value,
            $totalUnits->value,
            null,
            1,
            null,
        );
    }

    /** Reconstruye un kit leído de la base. Lo usa solo el repositorio. */
    public static function restore(
        string $id,
        string $name,
        string $manufacturer,
        KitReference $reference,
        string $categoryId,
        int $totalUnits,
        int $availableUnits,
        ?string $photoKey,
        int $version,
        ?DateTimeImmutable $deletedAt,
    ): self {
        if ($availableUnits < 0 || $availableUnits > $totalUnits) {
            throw new LogicException("Kit {$id} leído con unidades fuera de rango.");
        }

        return new self($id, $name, $manufacturer, $reference, $categoryId, $totalUnits, $availableUnits, $photoKey, $version, $deletedAt);
    }

    // ---------- Comportamiento ----------

    /** Descuenta una unidad al prestar (CA-06.3). */
    public function withdraw(): void
    {
        if (! $this->canWithdraw()) {
            throw KitNotAvailable::named($this->name);
        }
        $this->availableUnits--;
    }

    /** Suma una unidad al devolver (CA-07.1). Nunca supera el total (CA-07.3). */
    public function restock(): void
    {
        if (! $this->canRestock()) {
            throw new LogicException("Kit {$this->id}: devolver superaría el total de unidades.");
        }
        $this->availableUnits++;
    }

    /** Cambia el total y mueve las disponibles en la misma diferencia (CA-03.3). */
    public function changeTotal(Units $newTotal): void
    {
        $loaned = $this->loanedUnits();
        if ($newTotal->value < $loaned) {
            throw TotalBelowLoaned::because($newTotal->value, $loaned);
        }
        $this->totalUnits = $newTotal->value;
        $this->availableUnits = $newTotal->value - $loaned;
    }

    /** Edita los datos descriptivos (HU-03). Los préstamos pasados no cambian: guardan su copia (RN-4). */
    public function updateDetails(string $name, string $manufacturer, KitReference $reference, string $categoryId): void
    {
        $this->name = self::cleanName($name);
        $this->manufacturer = self::cleanManufacturer($manufacturer);
        $this->reference = $reference;
        $this->categoryId = $categoryId;
    }

    /** Baja lógica (CA-03.5, ADR-003). No se puede con unidades prestadas. Repetirla no cambia nada. */
    public function remove(DateTimeImmutable $now): void
    {
        if (! $this->isActive()) {
            return;
        }
        if ($this->loanedUnits() > 0) {
            throw KitHasOpenLoans::withLoaned($this->loanedUnits());
        }
        $this->deletedAt = $now;
    }

    /** Guarda la clave opaca de la foto; nunca cadena vacía (CA-03.4). */
    public function attachPhoto(string $photoKey): void
    {
        if (trim($photoKey) === '') {
            throw new LogicException('La clave de la foto no puede ser vacía.');
        }
        $this->photoKey = $photoKey;
    }

    public function detachPhoto(): void
    {
        $this->photoKey = null;
    }

    // ---------- Lectura ----------

    /** ¿Se puede prestar una unidad? Activo y con al menos una disponible. */
    public function canWithdraw(): bool
    {
        return $this->isActive() && $this->availableUnits > 0;
    }

    /** ¿Se puede devolver una unidad sin pasar del total? */
    public function canRestock(): bool
    {
        return $this->availableUnits < $this->totalUnits;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    public function loanedUnits(): int
    {
        return $this->totalUnits - $this->availableUnits;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function manufacturer(): string
    {
        return $this->manufacturer;
    }

    public function reference(): KitReference
    {
        return $this->reference;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalUnits(): int
    {
        return $this->totalUnits;
    }

    public function availableUnits(): int
    {
        return $this->availableUnits;
    }

    public function photoKey(): ?string
    {
        return $this->photoKey;
    }

    /** La versión que se leyó. El repositorio la usa en el WHERE y la sube al guardar (ADR-002). */
    public function version(): int
    {
        return $this->version;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    // ---------- Validaciones de texto ----------

    private static function cleanName(string $name): string
    {
        return self::cleanText($name, 'name', 'nombre', self::NAME_MAX);
    }

    private static function cleanManufacturer(string $manufacturer): string
    {
        return self::cleanText($manufacturer, 'manufacturer', 'fabricante', self::MANUFACTURER_MAX);
    }

    private static function cleanText(string $value, string $field, string $label, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationError::forField(
                $field,
                "entre 1 y {$max} caracteres",
                "El {$label} del kit es obligatorio y puede tener máximo {$max} caracteres.",
            );
        }

        return $value;
    }
}
