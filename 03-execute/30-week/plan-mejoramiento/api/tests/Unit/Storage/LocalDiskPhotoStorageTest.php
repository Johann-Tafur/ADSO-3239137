<?php

namespace Tests\Unit\Storage;

use App\Adapters\Outbound\Storage\LocalDiskPhotoStorage;
use App\Application\Results\Photo;
use App\Application\Services\CreateKitService;
use App\Application\Services\KitPhotoService;
use App\Domain\Errors\ValidationError;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCategoryRepository;
use Tests\Support\InMemoryKitRepository;

/** T-15 · Fotos en disco (CA-03.4, plan D-08): PNG de 2 MB aceptado; 3 MB → 422; sin foto → photo_key nulo. */
final class LocalDiskPhotoStorageTest extends TestCase
{
    private const MB = 1024 * 1024;

    private string $directory;

    private LocalDiskPhotoStorage $storage;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'robotics-photos-'.bin2hex(random_bytes(4));
        $this->storage = new LocalDiskPhotoStorage($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /** Bytes que empiezan como un PNG de verdad, rellenados hasta el tamaño pedido. */
    private static function png(int $bytes): Photo
    {
        $header = "\x89PNG\r\n\x1a\n";

        return new Photo($header.str_repeat("\0", $bytes - strlen($header)), 'image/png');
    }

    /** @return array{KitPhotoService, InMemoryKitRepository, string} */
    private function serviceWithKit(): array
    {
        $categories = new InMemoryCategoryRepository;
        $kits = new InMemoryKitRepository($categories);
        $kitId = (new CreateKitService($kits, $categories))
            ->create('Arduino', 'Arduino', 'ARD-0001', InMemoryCategoryRepository::INICIACION, 1)->kit->id();

        return [new KitPhotoService($kits, $this->storage), $kits, $kitId];
    }

    private function filesOnDisk(): int
    {
        return count(glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: []);
    }

    // ---------- La evidencia de T-15 ----------

    public function test_a_png_of_2_mb_is_accepted_and_written_to_disk(): void
    {
        [$service, $kits, $kitId] = $this->serviceWithKit();

        $service->upload($kitId, self::png(2 * self::MB));

        $key = $kits->stored($kitId)->photoKey();
        $this->assertTrue(LocalDiskPhotoStorage::isValidKey($key));
        $this->assertSame(2 * self::MB, filesize($this->directory.DIRECTORY_SEPARATOR.$key));
        $this->assertSame('image/png', $service->read($kitId)->mimeType);
    }

    public function test_a_photo_of_3_mb_is_a_validation_error_and_nothing_is_written(): void
    {
        [$service, $kits, $kitId] = $this->serviceWithKit();

        try {
            $service->upload($kitId, self::png(3 * self::MB));
            $this->fail('Debió rechazar 3 MB');
        } catch (ValidationError $error) {
            $this->assertSame('VALIDATION_ERROR', $error->errorCode()); // el adaptador HTTP lo convierte en 422
        }
        $this->assertSame(0, $this->filesOnDisk());
        $this->assertNull($kits->stored($kitId)->photoKey());
    }

    public function test_without_photo_the_key_is_null_never_an_empty_string(): void
    {
        [$service, $kits, $kitId] = $this->serviceWithKit();
        $this->assertNull($kits->stored($kitId)->photoKey());

        $service->upload($kitId, self::png(100));
        $service->remove($kitId);

        $this->assertNull($kits->stored($kitId)->photoKey());
        $this->assertSame(0, $this->filesOnDisk(), 'quitar la foto también borra el archivo');
    }

    // ---------- El adaptador ----------

    public function test_the_key_is_opaque_uuid_plus_extension_never_a_path(): void
    {
        $png = $this->storage->store(self::png(10));
        $jpg = $this->storage->store(new Photo('jpg', 'image/jpeg'));

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $png);
        $this->assertStringEndsWith('.jpg', $jpg);
        $this->assertStringNotContainsString(DIRECTORY_SEPARATOR, $png);
        $this->assertNotSame($png, $this->storage->store(self::png(10)), 'cada foto tiene su propia clave');
    }

    public function test_reading_gives_back_the_same_bytes_and_type(): void
    {
        $key = $this->storage->store(new Photo('bytes-de-un-jpg', 'image/jpeg'));

        $photo = $this->storage->read($key);

        $this->assertSame('bytes-de-un-jpg', $photo->contents);
        $this->assertSame('image/jpeg', $photo->mimeType);
    }

    public function test_keys_that_try_to_escape_the_folder_are_refused(): void
    {
        file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'secreto.png', 'no deberías leer esto');

        foreach (['../secreto.png', '..\\secreto.png', '/etc/passwd', 'secreto.png', '', 'x.png'] as $evil) {
            $this->assertNull($this->storage->read($evil), "«{$evil}» no debe leerse");
            $this->storage->delete($evil); // tampoco borra nada
        }
        $this->assertFileExists(sys_get_temp_dir().DIRECTORY_SEPARATOR.'secreto.png');
        unlink(sys_get_temp_dir().DIRECTORY_SEPARATOR.'secreto.png');
    }

    public function test_deleting_a_missing_key_is_not_an_error(): void
    {
        $key = $this->storage->store(self::png(10));
        $this->storage->delete($key);
        $this->storage->delete($key);

        $this->assertNull($this->storage->read($key));
    }
}
