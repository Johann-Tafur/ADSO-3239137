<?php

namespace Tests\Integration;

use App\Adapters\Outbound\Persistence\PostgresKitRepository;
use App\Application\Paging\PageRequest;
use App\Application\Results\KitView;
use App\Domain\Errors\KitChanged;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use DateTimeImmutable;
use Illuminate\Database\QueryException;

/** T-12: PostgresKitRepository contra PostgreSQL real (ADR-002, ADR-003, CA-04.1). */
final class KitRepositoryTest extends IntegrationTestCase
{
    private const INICIACION = '11111111-1111-4111-8111-111111111111';

    private const SENSORES = '22222222-2222-4222-8222-222222222222';

    private PostgresKitRepository $kits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kits = new PostgresKitRepository($this->db());
    }

    private function newKit(string $name, string $reference, string $category = self::INICIACION, int $units = 3, string $manufacturer = 'Fabricante'): Kit
    {
        $kit = Kit::create($this->kits->nextId(), $name, $manufacturer, new KitReference($reference), $category, new Units($units));
        $this->kits->add($kit);

        return $kit;
    }

    /** @param list<KitView> $views @return list<string> */
    private static function names(array $views): array
    {
        return array_map(fn (KitView $view) => $view->kit->name(), $views);
    }

    // ---------- Guardar y leer ----------

    public function test_a_kit_is_saved_and_read_back_with_every_field(): void
    {
        $kit = $this->newKit('Arduino Starter Kit', 'ARD-0001', units: 4);

        $read = $this->kits->findActive($kit->id());

        $this->assertSame('Arduino Starter Kit', $read->name());
        $this->assertSame('ARD-0001', $read->reference()->value);
        $this->assertSame(self::INICIACION, $read->categoryId());
        $this->assertSame([4, 4, 1], [$read->totalUnits(), $read->availableUnits(), $read->version()]);
        $this->assertNull($read->photoKey());
        $this->assertNull($read->deletedAt());
    }

    public function test_ids_are_uuid_v4_and_a_non_uuid_id_is_simply_not_found(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $this->kits->nextId());
        $this->assertNull($this->kits->findActive('no-es-un-uuid'));
    }

    // ---------- Concurrencia optimista (ADR-002) ----------

    public function test_save_raises_the_version_by_one(): void
    {
        $kit = $this->newKit('Arduino', 'ARD-0001');
        $read = $this->kits->findActive($kit->id());
        $read->withdraw();
        $this->kits->save($read);

        $again = $this->kits->findActive($kit->id());
        $this->assertSame(2, $again->version());
        $this->assertSame(2, $again->availableUnits());
    }

    public function test_saving_a_stale_copy_affects_zero_rows_and_is_kit_changed(): void
    {
        $kit = $this->newKit('Arduino', 'ARD-0001');
        $first = $this->kits->findActive($kit->id());
        $late = $this->kits->findActive($kit->id()); // misma versión leída
        $first->withdraw();
        $this->kits->save($first);

        $late->withdraw();
        try {
            $this->kits->save($late);
            $this->fail('Debió detectar la versión vieja');
        } catch (KitChanged $error) {
            $this->assertSame('KIT_CHANGED', $error->errorCode());
        }
        $this->assertSame(2, $this->kits->findActive($kit->id())->availableUnits(), 'solo se descontó una unidad');
    }

    public function test_the_database_refuses_impossible_units_even_if_the_code_failed(): void
    {
        $kit = $this->newKit('Arduino', 'ARD-0001', units: 2);

        $this->expectException(QueryException::class); // ck_kit_available_range
        $this->db()->update('UPDATE kit SET available_units = 3 WHERE id = ?', [$kit->id()]);
    }

    // ---------- Referencia única entre activos (CA-03.2, ADR-003) ----------

    public function test_a_repeated_active_reference_is_reference_taken(): void
    {
        $this->newKit('Arduino', 'ARD-0001');

        $this->expectException(ReferenceTaken::class);
        $this->newKit('Otro', 'ARD-0001');
    }

    public function test_changing_to_a_reference_already_taken_is_reference_taken(): void
    {
        $this->newKit('Arduino', 'ARD-0001');
        $other = $this->kits->findActive($this->newKit('Dron', 'DRN-0001')->id());
        $other->updateDetails('Dron', 'DJI', new KitReference('ARD-0001'), self::INICIACION);

        $this->expectException(ReferenceTaken::class);
        $this->kits->save($other);
    }

    public function test_a_removed_kit_disappears_but_its_row_stays_and_its_reference_is_free(): void
    {
        $kit = $this->kits->findActive($this->newKit('Arduino', 'ARD-0001')->id());
        $kit->remove(new DateTimeImmutable('2026-11-17T10:00:00Z'));
        $this->kits->save($kit);

        $this->assertNull($this->kits->findActive($kit->id()));
        $this->assertSame(0, $this->kits->search(null, null, new PageRequest)->total);
        $this->assertSame(1, (int) $this->db()->selectOne('SELECT count(*) AS n FROM kit WHERE id = ?', [$kit->id()])->n);

        $this->newKit('Arduino nuevo', 'ARD-0001'); // la referencia quedó libre
        $this->assertSame('Arduino nuevo', $this->kits->findActiveByReference(new KitReference('ARD-0001'))->name());
    }

    // ---------- Búsqueda (CA-04.1, CA-04.2) ----------

    private function seedCatalog(): void
    {
        $this->newKit('Zumo robot', 'ZUM-0001', self::INICIACION, manufacturer: 'Pololu');
        $this->newKit('arduino nano', 'ARD-0002', self::INICIACION, manufacturer: 'Arduino');
        $this->newKit('Órbita satélite', 'ORB-0001', self::SENSORES, manufacturer: 'Kit 100% educativo');
        $this->newKit('Sensor ultrasónico', 'SEN-0001', self::SENSORES, manufacturer: 'Elegoo');
    }

    public function test_search_orders_by_name_as_a_person_would(): void
    {
        $this->seedCatalog();

        $page = $this->kits->search(null, null, new PageRequest);

        $this->assertSame(['arduino nano', 'Órbita satélite', 'Sensor ultrasónico', 'Zumo robot'], self::names($page->items));
        $this->assertSame(4, $page->total);
    }

    public function test_search_by_partial_text_in_name_or_manufacturer_ignoring_case(): void
    {
        $this->seedCatalog();

        $this->assertSame(['arduino nano'], self::names($this->kits->search('ARDU', null, new PageRequest)->items));
        $this->assertSame(['Sensor ultrasónico'], self::names($this->kits->search('elegoo', null, new PageRequest)->items));
    }

    public function test_percent_and_underscore_are_searched_literally(): void
    {
        $this->seedCatalog();

        $this->assertSame(['Órbita satélite'], self::names($this->kits->search('100%', null, new PageRequest)->items));
        $this->assertSame(0, $this->kits->search('_', null, new PageRequest)->total);
    }

    public function test_search_by_category_brings_the_category_name(): void
    {
        $this->seedCatalog();

        $page = $this->kits->search(null, self::SENSORES, new PageRequest);

        $this->assertSame(['Órbita satélite', 'Sensor ultrasónico'], self::names($page->items));
        $this->assertSame('Sensores', $page->items[0]->category->name);
    }

    public function test_pages_keep_the_total(): void
    {
        $this->seedCatalog();

        $page = $this->kits->search(null, null, new PageRequest(page: 2, size: 3));

        $this->assertSame(['Zumo robot'], self::names($page->items));
        $this->assertSame(4, $page->total);
    }

    public function test_filtering_by_category_can_use_the_partial_index_of_the_data_model(): void
    {
        // Con 4 filas PostgreSQL prefiere leer la tabla entera; se le prohíbe para ver si el índice sirve
        $this->db()->statement('SET LOCAL enable_seqscan = off');
        $plan = implode("\n", array_map(
            fn (object $row) => $row->{'QUERY PLAN'},
            $this->db()->select('EXPLAIN SELECT id FROM kit WHERE deleted_at IS NULL AND category_id = ?', [self::SENSORES]),
        ));

        $this->assertStringContainsString('ix_kit_category_name_active', $plan);
    }
}
