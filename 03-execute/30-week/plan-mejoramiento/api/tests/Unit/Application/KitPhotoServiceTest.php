<?php

namespace Tests\Unit\Application;

use App\Application\Results\Photo;
use App\Application\Services\CreateKitService;
use App\Application\Services\KitPhotoService;
use App\Domain\Errors\KitChanged;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCategoryRepository;
use Tests\Support\InMemoryKitRepository;
use Tests\Support\InMemoryPhotoStorage;

/** T-08 · HU-03: foto opcional del kit (CA-03.4), contra fakes. */
final class KitPhotoServiceTest extends TestCase
{
    private const TWO_MB = 2 * 1024 * 1024;

    private InMemoryKitRepository $kits;

    private InMemoryPhotoStorage $photos;

    private KitPhotoService $service;

    private string $kitId;

    protected function setUp(): void
    {
        $categories = new InMemoryCategoryRepository;
        $this->kits = new InMemoryKitRepository($categories);
        $this->photos = new InMemoryPhotoStorage;
        $this->service = new KitPhotoService($this->kits, $this->photos);

        $this->kitId = (new CreateKitService($this->kits, $categories))
            ->create('Arduino', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 1)->kit->id();
    }

    private static function png(int $bytes): Photo
    {
        return new Photo(str_repeat('x', $bytes), 'image/png');
    }

    public function test_a_png_of_exactly_2_mb_is_accepted_and_the_kit_keeps_only_its_key(): void
    {
        $this->service->upload($this->kitId, self::png(self::TWO_MB));

        $key = $this->kits->stored($this->kitId)->photoKey();
        $this->assertNotNull($key);
        $this->assertSame(self::TWO_MB, $this->service->read($this->kitId)->sizeInBytes());
    }

    /** @return array<string, array{Photo}> */
    public static function invalidPhotos(): array
    {
        return [
            'más de 2 MB' => [new Photo(str_repeat('x', self::TWO_MB + 1), 'image/png')],
            'vacía' => [new Photo('', 'image/png')],
            'GIF' => [new Photo('GIF89a', 'image/gif')],
            'PDF' => [new Photo('%PDF-1.7', 'application/pdf')],
        ];
    }

    #[DataProvider('invalidPhotos')]
    public function test_invalid_photos_are_rejected_and_nothing_is_stored(Photo $photo): void
    {
        try {
            $this->service->upload($this->kitId, $photo);
            $this->fail('Debió rechazar la foto');
        } catch (ValidationError $error) {
            $this->assertSame('file', $error->details()[0]['field']);
        }
        $this->assertSame(0, $this->photos->count());
        $this->assertNull($this->kits->stored($this->kitId)->photoKey());
    }

    public function test_a_jpg_is_accepted(): void
    {
        $this->service->upload($this->kitId, new Photo('jpg-bytes', 'image/jpeg'));

        $this->assertSame('image/jpeg', $this->service->read($this->kitId)->mimeType);
    }

    public function test_replacing_the_photo_deletes_the_old_one(): void
    {
        $this->service->upload($this->kitId, self::png(10));
        $this->service->upload($this->kitId, self::png(20));

        $this->assertSame(1, $this->photos->count());
        $this->assertSame(20, $this->service->read($this->kitId)->sizeInBytes());
    }

    public function test_removing_the_photo_leaves_null_never_an_empty_string(): void
    {
        $this->service->upload($this->kitId, self::png(10));
        $this->service->remove($this->kitId);

        $this->assertNull($this->kits->stored($this->kitId)->photoKey());
        $this->assertSame(0, $this->photos->count());
    }

    public function test_a_kit_without_photo_has_no_photo_to_read(): void
    {
        $this->expectException(NotFound::class);
        $this->service->read($this->kitId);
    }

    public function test_if_saving_the_kit_fails_the_new_photo_is_deleted(): void
    {
        // Simula que otra petición cambió el kit justo antes: la versión ya no coincide (ADR-002)
        $this->kits->failNextSave();

        try {
            $this->service->upload($this->kitId, self::png(10));
            $this->fail('Debió fallar al guardar');
        } catch (KitChanged) {
        }
        $this->assertSame(0, $this->photos->count(), 'no debe quedar una foto huérfana');
    }
}
