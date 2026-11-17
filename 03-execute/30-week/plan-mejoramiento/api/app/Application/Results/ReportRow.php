<?php

namespace App\Application\Results;

/**
 * Una fila del reporte: un kit con el nombre y la categoría CONGELADOS en la línea, y cuántas veces se prestó (CA-10.1).
 * No tiene participante ni mentor, a propósito (CA-10.3).
 */
final readonly class ReportRow
{
    public function __construct(
        public string $kitId,
        public string $kitName,
        public string $categoryName,
        public int $timesRequested,
    ) {}
}
