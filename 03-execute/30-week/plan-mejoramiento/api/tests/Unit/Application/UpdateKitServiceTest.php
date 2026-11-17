<?php

namespace Tests\Unit\Application;

use App\Application\Services\CreateKitService;
use App\Application\Services\UpdateKitService;
use App\Domain\Errors\KitChanged;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Errors\TotalBelowLoaned;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCategoryRepository;
use Tests\Support\InMemoryKitRepository;

/** T-08 · HU-03: editar un kit (CA-03.2, CA-03.3, ADR-002), contra fakes. */
final class UpdateKitServiceTest extends TestCase
{
    private const SENSORES = InMemoryCategoryRepository::SENSORES;

    private InMemoryKitRepository $kits;

    private UpdateKitService $service;

    private string $kitId;

    protected function setUp(): void
    {
        $categories = new InMemoryCategoryRepository;
        $this->kits = new InMemoryKitRepository($categories);
        $this->service = new UpdateKitService($this->kits, $categories);

        $this->kitId = (new CreateKitService($this->kits, $categories))
            ->create('Arduino', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 4)->kit->id();
    }

    /** Presta $units unidades directamente sobre lo guardado, como si hubiera préstamos abiertos. */
    private function lendUnits(int $units): void
    {
        $kit = $this->kits->findActive($this->kitId);
        for ($i = 0; $i < $units; $i++) {
            $kit->withdraw();
        }
        $this->kits->save($kit);
    }

    public function test_replaces_every_field_and_raises_the_version(): void
    {
        $view = $this->service->update($this->kitId, 'Arduino Mega', 'Arduino SRL', 'ARD-0002', self::SENSORES, 4);

        $stored = $this->kits->stored($this->kitId);
        $this->assertSame('Arduino Mega', $stored->name());
        $this->assertSame('ARD-0002', $stored->reference()->value);
        $this->assertSame(self::SENSORES, $stored->categoryId());
        $this->assertSame(2, $stored->version());
        $this->assertSame('Sensores', $view->category->name);
    }

    public function test_changing_the_total_moves_the_available_units_by_the_same_difference(): void
    {
        $this->lendUnits(1); // 4 en total, 3 disponibles

        $this->service->update($this->kitId, 'Arduino', 'Arduino', 'ARD-0001', self::SENSORES, 6);

        $this->assertSame(5, $this->kits->stored($this->kitId)->availableUnits());
    }

    public function test_a_total_below_the_loaned_units_is_rejected_and_nothing_is_saved(): void
    {
        $this->lendUnits(3);

        try {
            $this->service->update($this->kitId, 'Nombre nuevo', 'Arduino', 'ARD-0001', self::SENSORES, 2);
            $this->fail('Debió rechazar el total');
        } catch (TotalBelowLoaned $error) {
            $this->assertSame('TOTAL_BELOW_LOANED', $error->errorCode());
        }
        $this->assertSame('Arduino', $this->kits->stored($this->kitId)->name());
    }

    public function test_keeping_its_own_reference_is_allowed(): void
    {
        $this->service->update($this->kitId, 'Arduino Uno', 'Arduino', 'ARD-0001', self::SENSORES, 4);

        $this->assertSame('Arduino Uno', $this->kits->stored($this->kitId)->name());
    }

    public function test_taking_the_reference_of_another_active_kit_is_rejected(): void
    {
        $other = $this->kits->nextId();
        $this->kits->add(Kit::create($other, 'Dron', 'DJI', new KitReference('DRN-0001'), InMemoryCategoryRepository::DRONES, new Units(1)));

        $this->expectException(ReferenceTaken::class);
        $this->service->update($this->kitId, 'Arduino', 'Arduino', 'DRN-0001', self::SENSORES, 4);
    }

    public function test_an_unknown_kit_is_not_found(): void
    {
        $this->expectException(NotFound::class);
        $this->service->update('no-existe', 'Arduino', 'Arduino', 'ARD-0001', self::SENSORES, 4);
    }

    public function test_if_someone_changed_the_kit_first_the_save_is_rejected(): void
    {
        // Dos lecturas de la misma versión: la primera guarda, la segunda llega tarde (ADR-002)
        $first = $this->kits->findActive($this->kitId);
        $late = $this->kits->findActive($this->kitId);
        $first->withdraw();
        $this->kits->save($first);

        $late->changeTotal(new Units(10));
        $this->expectException(KitChanged::class);
        $this->kits->save($late);
    }
}
