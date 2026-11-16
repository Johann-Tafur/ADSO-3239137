<?php

namespace App\Application\Results;

/** Los bytes de una foto y su tipo (image/jpeg o image/png). */
final readonly class Photo
{
    public function __construct(
        public string $contents,
        public string $mimeType,
    ) {}

    public function sizeInBytes(): int
    {
        return strlen($this->contents);
    }
}
