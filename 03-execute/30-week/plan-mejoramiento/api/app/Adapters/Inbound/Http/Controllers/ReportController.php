<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Services\MostRequestedReportService;
use App\Domain\ValueObjects\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HU-10: reporte de kits más solicitados, solo admin (api-contract.md §3.7). Sin participantes ni mentores. */
final class ReportController extends ApiController
{
    public function mostRequested(Request $request, MostRequestedReportService $service): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'string'], 'to' => ['required', 'string']]);

        $report = $service->report(DateRange::fromDates($data['from'], $data['to']));

        return new JsonResponse([
            'from' => $report->range->startsAt()->format('Y-m-d'),
            'to' => $report->range->to->format('Y-m-d'),
            'rows' => array_map(fn ($row) => [
                'kit_id' => $row->kitId,
                'kit_name' => $row->kitName,
                'category_name' => $row->categoryName,
                'times_requested' => $row->timesRequested,
            ], $report->rows),
        ]);
    }
}
