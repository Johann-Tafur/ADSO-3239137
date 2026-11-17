<?php

namespace App\Application\Services;

use App\Application\Ports\ReportReader;
use App\Application\Results\MostRequestedReport;
use App\Application\Results\ReportRow;
use App\Application\Support\NameOrder;
use App\Domain\ValueObjects\DateRange;

/**
 * HU-10 · Reporte de kits más solicitados en un rango de fechas (solo admin).
 *
 * - Una fila por kit + nombre + categoría congelados; de más a menos veces y luego por nombre en orden alfabético
 *   español (CA-10.1). El orden lo decide este servicio, no la consulta: así no depende de la configuración de la base.
 * - Fin antes que inicio → INVALID_DATE_RANGE, que lanza DateRange. Rango sin préstamos → lista vacía (CA-10.2).
 * - No muestra participantes ni mentores (CA-10.3).
 * - Un periodo cerrado no cambia aunque después el kit se renombre o pase a otra categoría (CA-10.4, artículo VIII).
 *   Las categorías en sí son fijas y no se renombran.
 */
final class MostRequestedReportService
{
    public function __construct(private readonly ReportReader $reader) {}

    public function report(DateRange $range): MostRequestedReport
    {
        $rows = $this->reader->mostRequested($range);
        usort($rows, fn (ReportRow $a, ReportRow $b) => $b->timesRequested <=> $a->timesRequested
            ?: NameOrder::compare($a->kitName, $b->kitName));

        return new MostRequestedReport($range, $rows);
    }
}
