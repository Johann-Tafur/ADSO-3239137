<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\ReportReader;
use App\Application\Results\ReportRow;
use App\Domain\ValueObjects\DateRange;
use Illuminate\Database\ConnectionInterface;

/**
 * ReportReader con UNA consulta (plan.md D-06): agrupa las líneas por lo congelado (artículo VIII).
 * No lee la tabla kit ni category: un kit renombrado después no cambia un periodo cerrado (CA-10.4).
 * No selecciona participante ni usuario (CA-10.3). El orden lo pone MostRequestedReportService.
 */
final class PostgresReportReader implements ReportReader
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function mostRequested(DateRange $range): array
    {
        $rows = $this->db->select(
            'SELECT i.kit_id, i.kit_name, i.category_name, count(*) AS times_requested
               FROM loan_item i
               JOIN loan l ON l.id = i.loan_id
              WHERE l.loaned_at >= ? AND l.loaned_at < ?
              GROUP BY i.kit_id, i.kit_name, i.category_name',
            [Sql::toDb($range->startsAt()), Sql::toDb($range->endsBefore())],
        );

        return array_map(
            fn (object $row) => new ReportRow($row->kit_id, $row->kit_name, $row->category_name, (int) $row->times_requested),
            $rows,
        );
    }
}
