<?php

namespace App\Application\Ports;

use App\Application\Results\ReportRow;
use App\Domain\ValueObjects\DateRange;

/**
 * Puerto de lectura del reporte (plan.md D-06). El adaptador real hace UNA consulta:
 *
 *   SELECT i.kit_id, i.kit_name, i.category_name, count(*) AS times_requested
 *     FROM robotics.loan_item i
 *     JOIN robotics.loan l ON l.id = i.loan_id          -- loaned_at está en loan, no en loan_item
 *    WHERE l.loaned_at >= :inicio AND l.loaned_at < :fin_mas_un_dia   -- usa ix_loan_loaned_at
 *    GROUP BY i.kit_id, i.kit_name, i.category_name
 *
 * Lee lo congelado en la línea, nunca el catálogo vivo (artículo VIII).
 */
interface ReportReader
{
    /** @return list<ReportRow> en cualquier orden; el servicio los ordena */
    public function mostRequested(DateRange $range): array;
}
