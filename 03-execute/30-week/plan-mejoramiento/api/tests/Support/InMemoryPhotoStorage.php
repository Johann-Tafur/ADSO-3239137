<?php

namespace Tests\Support;

use App\Application\Ports\PhotoStorage;
use App\Application\Results\Photo;

/** Fake de PhotoStorage: guarda las fotos en un arreglo con claves opacas. */
final class InMemoryPhotoStorage implements PhotoStorage
{
    /** @var array<string, Photo> */
    private array $photos = [];

    private int $sequence = 0;

    public function store(Photo $photo): string
    {
        $key = 'photo-'.++$this->sequence;
        $this->photos[$key] = $photo;

        return $key;
    }

    public function read(string $key): ?Photo
    {
        return $this->photos[$key] ?? null;
    }

    public function delete(string $key): void
    {
        unset($this->photos[$key]);
    }

    public function count(): int
    {
        return count($this->photos);
    }
}
