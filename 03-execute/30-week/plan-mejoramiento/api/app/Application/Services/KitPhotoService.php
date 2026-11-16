<?php

namespace App\Application\Services;

use App\Application\Ports\KitRepository;
use App\Application\Ports\PhotoStorage;
use App\Application\Results\Photo;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use Throwable;

/**
 * HU-03 · Foto opcional del kit (CA-03.4): JPG o PNG, máximo 2 MB.
 *
 * La foto vive fuera de la base; el kit solo guarda su clave opaca. Orden al subir:
 * guardar la foto nueva → guardar el kit → recién entonces borrar la vieja.
 * Si guardar el kit falla, se borra la nueva y el kit queda con la anterior: nunca apunta a una foto que no existe.
 *
 * El tipo lo detecta el adaptador HTTP mirando los bytes, no lo que dice el navegador.
 */
final class KitPhotoService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const ALLOWED_TYPES = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly KitRepository $kits,
        private readonly PhotoStorage $photos,
    ) {}

    public function upload(string $kitId, Photo $photo): void
    {
        // 1. Validar la foto y encontrar el kit
        if (! in_array($photo->mimeType, self::ALLOWED_TYPES, true)) {
            throw ValidationError::forField('file', 'JPG o PNG', 'La foto debe ser JPG o PNG.');
        }
        if ($photo->sizeInBytes() === 0 || $photo->sizeInBytes() > self::MAX_BYTES) {
            throw ValidationError::forField('file', 'máximo 2 MB', 'La foto puede pesar máximo 2 MB.');
        }
        $kit = $this->kits->findActive($kitId) ?? throw NotFound::resource('el kit');
        $oldKey = $kit->photoKey();

        // 2. Guardar la nueva y apuntar el kit a ella
        $newKey = $this->photos->store($photo);
        try {
            $kit->attachPhoto($newKey);
            $this->kits->save($kit);
        } catch (Throwable $error) {
            $this->photos->delete($newKey);
            throw $error;
        }

        // 3. Ya nadie apunta a la vieja
        if ($oldKey !== null) {
            $this->photos->delete($oldKey);
        }
    }

    /** Quita la foto; sin foto queda null, nunca cadena vacía (CA-03.4). */
    public function remove(string $kitId): void
    {
        $kit = $this->kits->findActive($kitId) ?? throw NotFound::resource('el kit');
        $oldKey = $kit->photoKey();
        if ($oldKey === null) {
            return;
        }
        $kit->detachPhoto();
        $this->kits->save($kit);
        $this->photos->delete($oldKey);
    }

    /** La foto de un kit activo, o NOT_FOUND si no tiene. */
    public function read(string $kitId): Photo
    {
        $kit = $this->kits->findActive($kitId) ?? throw NotFound::resource('el kit');
        $key = $kit->photoKey();

        return ($key === null ? null : $this->photos->read($key)) ?? throw NotFound::resource('la foto del kit');
    }
}
