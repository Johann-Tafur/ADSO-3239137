<?php

namespace App\Application\Ports;

use App\Application\Results\Photo;

/** Guarda fotos fuera de la base y entrega una clave opaca (CA-03.4). El adaptador real usa el disco (T-15). */
interface PhotoStorage
{
    /** Guarda la foto y devuelve su clave; nunca una ruta del servidor. */
    public function store(Photo $photo): string;

    public function read(string $key): ?Photo;

    /** Borrar una clave que no existe no es un error. */
    public function delete(string $key): void;
}
