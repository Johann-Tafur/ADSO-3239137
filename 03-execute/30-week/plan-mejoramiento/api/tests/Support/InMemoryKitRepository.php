<?php

namespace Tests\Support;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\KitRepository;
use App\Application\Results\KitView;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;

/**
 * Fake de KitRepository. Se porta como la base:
 * - guarda COPIAS: si un servicio cambia un kit y olvida llamar save(), el cambio se pierde, igual que en PostgreSQL;
 * - imita la concurrencia optimista: save() exige la misma versión y la sube en 1 (ADR-002);
 * - imita el índice único parcial de referencia entre kits activos.
 */
final class InMemoryKitRepository implements KitRepository, Snapshottable
{
    /** @var array<string, Kit> */
    private array $stored = [];

    private int $sequence = 0;

    private bool $failNextSave = false;

    private ?string $failNextSaveOf = null;

    public function __construct(private readonly InMemoryCategoryRepository $categories) {}

    public function nextId(): string
    {
        return 'kit-'.++$this->sequence;
    }

    public function findActive(string $id): ?Kit
    {
        $kit = $this->stored[$id] ?? null;

        return $kit !== null && $kit->isActive() ? self::copy($kit, $kit->version()) : null;
    }

    public function findActiveByReference(KitReference $reference): ?Kit
    {
        foreach ($this->stored as $kit) {
            if ($kit->isActive() && $kit->reference()->equals($reference)) {
                return self::copy($kit, $kit->version());
            }
        }

        return null;
    }

    public function search(?string $text, ?string $categoryId, PageRequest $page): Page
    {
        $found = array_filter($this->stored, function (Kit $kit) use ($text, $categoryId) {
            $matchesText = $text === null
                || mb_stripos($kit->name(), $text) !== false
                || mb_stripos($kit->manufacturer(), $text) !== false;

            return $kit->isActive() && $matchesText && ($categoryId === null || $kit->categoryId() === $categoryId);
        });
        usort($found, fn (Kit $a, Kit $b) => strcmp($a->name(), $b->name()));

        $items = array_map(
            fn (Kit $kit) => new KitView(self::copy($kit, $kit->version()), $this->categories->find($kit->categoryId())),
            array_slice($found, $page->offset(), $page->size),
        );

        return new Page($items, $page->page, $page->size, count($found));
    }

    public function add(Kit $kit): void
    {
        $this->assertReferenceFree($kit);
        $this->stored[$kit->id()] = self::copy($kit, $kit->version());
    }

    public function save(Kit $kit): void
    {
        $current = $this->stored[$kit->id()] ?? null;
        $lostTheRace = $this->failNextSave || $this->failNextSaveOf === $kit->id();
        $this->failNextSave = false;
        if ($this->failNextSaveOf === $kit->id()) {
            $this->failNextSaveOf = null;
        }
        if ($lostTheRace || $current === null || $current->version() !== $kit->version()) {
            throw KitNotAvailable::named($kit->name());
        }
        $this->assertReferenceFree($kit);
        $this->stored[$kit->id()] = self::copy($kit, $kit->version() + 1);
    }

    /** Para las pruebas: el próximo save() pierde la carrera, como si otra petición hubiera guardado antes. */
    public function failNextSave(): void
    {
        $this->failNextSave = true;
    }

    /** Para las pruebas: el kit tal como quedó guardado, aunque esté dado de baja. */
    public function stored(string $id): ?Kit
    {
        return $this->stored[$id] ?? null;
    }

    /** Los kits guardados nunca se modifican (save() guarda una copia nueva), así que basta copiar el arreglo. */
    public function snapshot(): mixed
    {
        return $this->stored;
    }

    public function restoreSnapshot(mixed $snapshot): void
    {
        $this->stored = $snapshot;
    }

    /** Para las pruebas: el próximo save() de este kit pierde la carrera. */
    public function failNextSaveOf(string $kitId): void
    {
        $this->failNextSaveOf = $kitId;
    }

    private function assertReferenceFree(Kit $kit): void
    {
        if (! $kit->isActive()) {
            return;
        }
        foreach ($this->stored as $other) {
            if ($other->id() !== $kit->id() && $other->isActive() && $other->reference()->equals($kit->reference())) {
                throw ReferenceTaken::reference($kit->reference()->value);
            }
        }
    }

    private static function copy(Kit $kit, int $version): Kit
    {
        return Kit::restore(
            $kit->id(),
            $kit->name(),
            $kit->manufacturer(),
            $kit->reference(),
            $kit->categoryId(),
            $kit->totalUnits(),
            $kit->availableUnits(),
            $kit->photoKey(),
            $version,
            $kit->deletedAt(),
        );
    }
}
