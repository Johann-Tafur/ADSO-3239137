<?php

namespace Tests\Unit\Application;

use App\Application\Paging\PageRequest;
use App\Application\Results\KitView;
use App\Application\Services\CreateKitService;
use App\Application\Services\RemoveKitService;
use App\Application\Services\SearchKitsService;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryCategoryRepository as Categories;
use Tests\Support\InMemoryKitRepository;

/** T-08 · HU-04: buscar kits (CA-04.1, CA-04.2, CA-04.3), contra fakes. */
final class SearchKitsServiceTest extends TestCase
{
    private InMemoryKitRepository $kits;

    private SearchKitsService $service;

    /** @var array<string, string> referencia → id */
    private array $ids = [];

    protected function setUp(): void
    {
        $categories = new Categories;
        $this->kits = new InMemoryKitRepository($categories);
        $this->service = new SearchKitsService($this->kits, $categories);

        $create = new CreateKitService($this->kits, $categories);
        foreach ([
            ['Sensor ultrasónico', 'Elegoo', 'SEN-0001', Categories::SENSORES],
            ['Arduino Starter Kit', 'Arduino', 'ARD-0001', Categories::INICIACION],
            ['Dron Tello', 'DJI', 'DRN-0001', Categories::DRONES],
            ['Kit de sensores', 'Arduino', 'SEN-0002', Categories::SENSORES],
        ] as [$name, $manufacturer, $reference, $category]) {
            $this->ids[$reference] = $create->create($name, $manufacturer, $reference, $category, 1)->kit->id();
        }
    }

    /** @param list<KitView> $items @return list<string> */
    private function names(array $items): array
    {
        return array_map(fn (KitView $view) => $view->kit->name(), $items);
    }

    public function test_without_filters_it_lists_every_active_kit_by_name(): void
    {
        $page = $this->service->search(null, null, new PageRequest);

        $this->assertSame(['Arduino Starter Kit', 'Dron Tello', 'Kit de sensores', 'Sensor ultrasónico'], $this->names($page->items));
        $this->assertSame(4, $page->total);
    }

    public function test_partial_text_matches_name_or_manufacturer_ignoring_case(): void
    {
        $byName = $this->service->search('SENSOR', null, new PageRequest);
        $byManufacturer = $this->service->search('arduino', null, new PageRequest);

        $this->assertSame(['Kit de sensores', 'Sensor ultrasónico'], $this->names($byName->items));
        $this->assertSame(['Arduino Starter Kit', 'Kit de sensores'], $this->names($byManufacturer->items));
    }

    public function test_filters_by_category(): void
    {
        $page = $this->service->search(null, Categories::SENSORES, new PageRequest);

        $this->assertSame(['Kit de sensores', 'Sensor ultrasónico'], $this->names($page->items));
    }

    public function test_blank_text_is_the_same_as_no_text(): void
    {
        $this->assertSame(4, $this->service->search('   ', null, new PageRequest)->total);
    }

    public function test_pages_keep_the_total_of_every_result(): void
    {
        $second = $this->service->search(null, null, new PageRequest(page: 2, size: 3));

        $this->assertSame(['Sensor ultrasónico'], $this->names($second->items));
        $this->assertSame(4, $second->total);
    }

    public function test_size_above_50_is_a_validation_error(): void
    {
        $this->expectException(ValidationError::class);
        new PageRequest(size: 51);
    }

    public function test_a_removed_kit_is_neither_listed_nor_found(): void
    {
        (new RemoveKitService($this->kits, new FixedClock('2026-11-16T15:00:00Z')))->remove($this->ids['DRN-0001']);

        $this->assertNotContains('Dron Tello', $this->names($this->service->search(null, null, new PageRequest)->items));
        $this->expectException(NotFound::class);
        $this->service->get($this->ids['DRN-0001']);
    }

    public function test_get_returns_the_kit_with_its_category(): void
    {
        $view = $this->service->get($this->ids['ARD-0001']);

        $this->assertSame('Arduino Starter Kit', $view->kit->name());
        $this->assertSame('Iniciación', $view->category->name);
    }

    public function test_the_five_categories_come_sorted_by_name(): void
    {
        $names = array_map(fn ($category) => $category->name, $this->service->categories());

        $this->assertSame(['Competencia', 'Drones', 'Impresión 3D', 'Iniciación', 'Sensores'], $names);
    }
}
