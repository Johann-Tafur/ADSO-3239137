<?php

namespace App\Adapters\Outbound\Storage;

use App\Application\Ports\PhotoStorage;
use App\Application\Results\Photo;
use RuntimeException;

/**
 * PhotoStorage en una carpeta del disco (plan D-08, PHOTOS_DIR, por defecto storage/app/photos).
 *
 * - La clave es opaca: un UUID nuevo más la extensión («3f2a…c1.png»). Nunca una ruta ni el nombre que subió el usuario.
 * - Una clave que no tenga exactamente esa forma se rechaza antes de tocar el disco. Así nadie puede pedir
 *   «../../.env» y leer archivos del servidor (path traversal).
 * - Cambiar a la nube = otro adaptador y una línea en la composición (artículo IV).
 */
final class LocalDiskPhotoStorage implements PhotoStorage
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    private const KEY_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(jpg|png)$/';

    public function __construct(private readonly string $directory) {}

    public function store(Photo $photo): string
    {
        $extension = self::EXTENSIONS[$photo->mimeType]
            ?? throw new \LogicException("Tipo de foto {$photo->mimeType} no permitido: KitPhotoService debió rechazarlo.");

        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear la carpeta de fotos.');
        }

        $key = self::uuid().'.'.$extension;
        if (file_put_contents($this->path($key), $photo->contents, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar la foto.');
        }

        return $key;
    }

    public function read(string $key): ?Photo
    {
        if (! self::isValidKey($key) || ! is_file($this->path($key))) {
            return null;
        }
        $contents = file_get_contents($this->path($key));

        return $contents === false ? null : new Photo($contents, array_search(pathinfo($key, PATHINFO_EXTENSION), self::EXTENSIONS, true));
    }

    public function delete(string $key): void
    {
        if (self::isValidKey($key) && is_file($this->path($key))) {
            unlink($this->path($key));
        }
    }

    public static function isValidKey(string $key): bool
    {
        return preg_match(self::KEY_PATTERN, $key) === 1;
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.$key;
    }

    /** UUID v4 con PHP puro: este adaptador no necesita Laravel. */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
