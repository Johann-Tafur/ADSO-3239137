<?php

namespace Tests\Unit\Application;

use App\Application\Paging\PageRequest;
use App\Application\Services\CreateKitService;
use App\Application\Services\RemoveKitService;
use App\Application\Services\SearchKitsService;
use App\Domain\Errors\KitHasOpenLoans;
use App\Domain\Errors\NotFound;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryCategoryRepository;
use Tests\Support\InMemoryKitRepository;

/** T-08 · HU-03: baja lógica de un kit (CA-03.5, ADR-003), contra fakes. */
final class RemoveKitServiceTest extends TestCase
{
    private InMemoryKitRepository $kits;

    private InMemoryCategoryRepository $categories;

    private RemoveKitService $service;

    private string $kitId;

    protected function setUp(): void
    {
        $this->categories = new InMemoryCategoryRepository;
        $this->kits = new InMemoryKitRepository($this->categories);
        $this->service = new RemoveKitService($this->kits, new FixedClock('2026-11-16T15:00:00Z'));

        $this->kitId = (new CreateKitService($this->kits, $this->categories))
            ->create('Arduino', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 2)->kit->id();
    }

    public function test_the_kit_is_kept_with_deleted_at_and_leaves_the_catalog(): void
    {
        $this->service->remove($this->kitId);

        $stored = $this->kits->stored($this->kitId);
        $this->assertNotNull($stored, 'la fila sigue existiendo: es baja lógica');
        $this->assertEquals(new \DateTimeImmutable('2026-11-16T15:00:00Z'), $stored->deletedAt());

        $catalog = (new SearchKitsService($this->kits, $this->categories))->search(null, null, new PageRequest);
        $this->assertSame(0, $catalog->total);
    }

    public function test_a_kit_with_loaned_units_cannot_be_removed(): void
    {
        $kit = $this->kits->findActive($this->kitId);
        $kit->withdraw();
        $this->kits->save($kit);

        $this->expectException(KitHasOpenLoans::class);
        $this->service->remove($this->kitId);
    }

    public function test_removing_it_again_is_not_found(): void
    {
        $this->service->remove($this->kitId);

        $this->expectException(NotFound::class);
        $this->service->remove($this->kitId);
    }

    public function test_its_reference_can_be_used_by_a_new_kit(): void
    {
        $this->service->remove($this->kitId);

        $view = (new CreateKitService($this->kits, $this->categories))
            ->create('Arduino nuevo', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 1);

        $this->assertNotSame($this->kitId, $view->kit->id());
    }
}
