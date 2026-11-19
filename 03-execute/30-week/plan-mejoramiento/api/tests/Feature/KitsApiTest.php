<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;

/** T-16 · HU-03 y HU-04 por HTTP: catálogo, categorías, baja lógica y fotos. */
final class KitsApiTest extends ApiTestCase
{
    /** Un PNG real de 1×1 píxel; se rellena al final para llegar al tamaño pedido (finfo lo sigue viendo como PNG). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private static function png(string $name, int $bytes): UploadedFile
    {
        $image = base64_decode(self::PNG_1X1);

        return UploadedFile::fake()->createWithContent($name, $image.str_repeat("\0", $bytes - strlen($image)));
    }

    public function test_categories_are_the_five_in_order(): void
    {
        $names = array_column($this->api('GET', '/categories', $this->mentor())->assertOk()->json('items'), 'name');

        $this->assertSame(['Competencia', 'Drones', 'Impresión 3D', 'Iniciación', 'Sensores'], $names);
    }

    public function test_creating_a_kit_returns_201_with_the_contract_shape(): void
    {
        $response = $this->api('POST', '/kits', $this->admin(), [
            'name' => 'Arduino Starter Kit', 'manufacturer' => 'Arduino', 'reference' => 'ARD-0001',
            'category_id' => self::INICIACION, 'total_units' => 4,
        ]);

        $response->assertCreated()
            ->assertJsonPath('category.name', 'Iniciación')
            ->assertJsonPath('total_units', 4)
            ->assertJsonPath('available_units', 4)
            ->assertJsonPath('photo_key', null);
        $this->assertSame(
            ['id', 'name', 'manufacturer', 'reference', 'category', 'total_units', 'available_units', 'photo_key'],
            array_keys($response->json()),
        );
    }

    public function test_a_bad_reference_is_422_and_a_repeated_one_is_409(): void
    {
        $admin = $this->admin();
        $this->createKit($admin, 'ARD-0001');

        $bad = $this->api('POST', '/kits', $admin, ['name' => 'X', 'manufacturer' => 'X', 'reference' => 'ard-1', 'category_id' => self::INICIACION, 'total_units' => 1]);
        $repeated = $this->api('POST', '/kits', $admin, ['name' => 'X', 'manufacturer' => 'X', 'reference' => 'ARD-0001', 'category_id' => self::INICIACION, 'total_units' => 1]);

        $this->assertError($bad, 422, 'VALIDATION_ERROR');
        $this->assertError($repeated, 409, 'REFERENCE_TAKEN');
    }

    public function test_search_by_text_and_category_paginated_with_total(): void
    {
        $admin = $this->admin();
        $this->createKit($admin, 'ARD-0001', name: 'Arduino Uno');
        $this->createKit($admin, 'ARD-0002', name: 'Arduino Nano');
        $this->createKit($admin, 'SEN-0001', category: self::SENSORES, name: 'Sensor de luz');

        $byText = $this->api('GET', '/kits?q=arduino&size=1&page=2', $this->mentor())->assertOk();
        $byCategory = $this->api('GET', '/kits?category_id='.self::SENSORES, $this->mentor())->assertOk();

        $byText->assertJsonPath('total', 2)->assertJsonPath('page', 2)->assertJsonPath('size', 1)->assertJsonPath('items.0.name', 'Arduino Uno');
        $this->assertSame(['Sensor de luz'], array_column($byCategory->json('items'), 'name'));
    }

    public function test_size_over_50_is_422(): void
    {
        $this->assertError($this->api('GET', '/kits?size=51', $this->mentor()), 422, 'VALIDATION_ERROR');
    }

    public function test_update_moves_available_units_and_below_loaned_is_422(): void
    {
        $admin = $this->admin();
        $kit = $this->createKit($admin, 'ARD-0001', 2);
        $body = ['name' => 'Arduino Mega', 'manufacturer' => 'Arduino', 'reference' => 'ARD-0001', 'category_id' => self::SENSORES];

        $this->api('PUT', "/kits/{$kit}", $admin, [...$body, 'total_units' => 5])
            ->assertOk()->assertJsonPath('available_units', 5)->assertJsonPath('category.name', 'Sensores');

        $participant = $this->createParticipant($admin, 'Ana', '111');
        $this->api('POST', '/loans', $admin, ['participant_id' => $participant, 'items' => [['kit_id' => $kit]]])->assertCreated();

        $this->assertError($this->api('PUT', "/kits/{$kit}", $admin, [...$body, 'total_units' => 0]), 422, 'VALIDATION_ERROR');
        $this->assertError($this->api('DELETE', "/kits/{$kit}", $admin), 409, 'KIT_HAS_OPEN_LOANS');
    }

    public function test_delete_is_logical_204_and_then_the_kit_is_404(): void
    {
        $admin = $this->admin();
        $kit = $this->createKit($admin, 'ARD-0001');

        $this->api('DELETE', "/kits/{$kit}", $admin)->assertNoContent();

        $this->assertError($this->api('GET', "/kits/{$kit}", $admin), 404, 'NOT_FOUND');
        $this->api('GET', '/kits', $admin)->assertJsonPath('total', 0);
    }

    public function test_an_id_that_is_not_a_uuid_is_404_not_500(): void
    {
        $this->assertError($this->api('GET', '/kits/hola', $this->mentor()), 404, 'NOT_FOUND');
    }

    public function test_photo_png_of_2_mb_is_accepted_served_and_removed(): void
    {
        $admin = $this->admin();
        $kit = $this->createKit($admin, 'ARD-0001');
        $png = self::png('foto.png', 2 * 1024 * 1024);

        $uploaded = $this->withHeader('Authorization', "Bearer {$admin}")->put(self::API."/kits/{$kit}/photo", ['file' => $png]);
        $uploaded->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $uploaded->json('photo_key'));

        $this->api('GET', "/kits/{$kit}/photo", $this->mentor())->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->api('DELETE', "/kits/{$kit}/photo", $admin)->assertNoContent();
        $this->api('GET', "/kits/{$kit}", $admin)->assertJsonPath('photo_key', null);
        $this->assertError($this->api('GET', "/kits/{$kit}/photo", $admin), 404, 'NOT_FOUND');
    }

    public function test_photo_of_3_mb_or_disguised_as_png_is_422(): void
    {
        $admin = $this->admin();
        $kit = $this->createKit($admin, 'ARD-0001');
        $big = self::png('grande.png', 3 * 1024 * 1024);
        $fake = UploadedFile::fake()->createWithContent('virus.png', '<?php echo "no soy una imagen";');

        foreach ([$big, $fake] as $file) {
            $response = $this->withHeader('Authorization', "Bearer {$admin}")->put(self::API."/kits/{$kit}/photo", ['file' => $file]);
            $this->assertError($response, 422, 'VALIDATION_ERROR');
        }
        $this->api('GET', "/kits/{$kit}", $admin)->assertJsonPath('photo_key', null);
    }
}
