<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Json;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendKitsService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\ValueObjects\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HU-06 a HU-09: préstamos, devoluciones y vencidos (api-contract.md §3.6). */
final class LoanController extends ApiController
{
    /** El préstamo lo registra el usuario del token, nunca uno que venga en el cuerpo (CA-06.1). */
    public function store(Request $request, LendKitsService $service): JsonResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'string'],
            'items' => ['required', 'array'],
            'items.*.kit_id' => ['required', 'string'],
        ]);
        $kitIds = array_map(fn (array $item) => $item['kit_id'], array_values($data['items']));

        $view = $service->lend($data['participant_id'], $kitIds, $this->claims($request)->userId);

        return new JsonResponse(Json::loan($view), 201);
    }

    public function index(Request $request, ListLoansService $service): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'string', 'required_with:to'],
            'to' => ['nullable', 'string', 'required_with:from'],
            'status' => ['nullable', 'string'],
            'participant_id' => ['nullable', 'string'],
        ]);
        $from = $this->optionalText($request, 'from');
        $to = $this->optionalText($request, 'to');
        $range = $from !== null && $to !== null ? DateRange::fromDates($from, $to) : null;

        $page = $service->list($range, $this->optionalText($request, 'status'), $this->optionalText($request, 'participant_id'), $this->pageRequest($request));

        return new JsonResponse(Json::page($page, Json::loan(...)));
    }

    public function overdue(OverdueService $service): JsonResponse
    {
        return new JsonResponse(['items' => array_map(Json::overdue(...), $service->list())]);
    }

    public function show(string $id, GetLoanService $service): JsonResponse
    {
        return new JsonResponse(Json::loan($service->get($id)));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return new JsonResponse(Json::item($service->returnItem($loanId, $itemId)));
    }
}
