<?php

namespace Tests\Unit\Application;

use App\Application\Services\CreateKitService;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Errors\ValidationError;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCategoryRepository;
use Tests\Support\InMemoryKitRepository;

/** T-08 · HU-03: crear un kit (CA-03.1, CA-03.2), contra fakes. */
final class CreateKitServiceTest extends TestCase
{
    private InMemoryKitRepository $kits;

    private CreateKitService $service;

    protected function setUp(): void
    {
        $categories = new InMemoryCategoryRepository;
        $this->kits = new InMemoryKitRepository($categories);
        $this->service = new CreateKitService($this->kits, $categories);
    }

    public function test_a_new_kit_starts_with_every_unit_available_and_version_1(): void
    {
        $view = $this->service->create('Arduino Starter Kit', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 4);

        $stored = $this->kits->stored($view->kit->id());
        $this->assertSame(4, $stored->totalUnits());
        $this->assertSame(4, $stored->availableUnits());
        $this->assertSame(1, $stored->version());
        $this->assertNull($stored->photoKey());
        $this->assertSame('Iniciación', $view->category->name);
    }

    public function test_a_reference_already_used_by_an_active_kit_is_taken(): void
    {
        $this->service->create('Arduino', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 1);

        $this->expectException(ReferenceTaken::class);
        $this->service->create('Otro', 'Otro', 'ARD-0001', InMemoryCategoryRepository::SENSORES, 1);
    }

    public function test_a_bad_reference_format_is_a_validation_error(): void
    {
        $this->expectException(ValidationError::class);
        $this->service->create('Arduino', 'Arduino', 'ard-1', InMemoryCategoryRepository::INICIACION, 1);
    }

    public function test_zero_units_is_a_validation_error(): void
    {
        $this->expectException(ValidationError::class);
        $this->service->create('Arduino', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 0);
    }

    public function test_an_unknown_category_is_a_validation_error(): void
    {
        try {
            $this->service->create('Arduino', 'Arduino', 'ARD-0001', 'no-existe', 1);
            $this->fail('Debió rechazar la categoría');
        } catch (ValidationError $error) {
            $this->assertSame('category_id', $error->details()[0]['field']);
        }
    }
}
