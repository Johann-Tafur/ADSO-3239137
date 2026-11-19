<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Json;
use App\Application\Services\CreateParticipantService;
use App\Application\Services\SearchParticipantsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HU-05: participantes (api-contract.md §3.5). No hay PUT ni DELETE (CA-05.3). */
final class ParticipantController extends ApiController
{
    public function index(Request $request, SearchParticipantsService $service): JsonResponse
    {
        $page = $service->search($this->optionalText($request, 'q'), $this->pageRequest($request));

        return new JsonResponse(Json::page($page, Json::participant(...)));
    }

    public function show(string $id, SearchParticipantsService $service): JsonResponse
    {
        $view = $service->get($id);

        return new JsonResponse([...Json::participant($view->participant), 'active_items' => $view->activeItems]);
    }

    public function store(Request $request, CreateParticipantService $service): JsonResponse
    {
        $data = $request->validate(['full_name' => ['required', 'string'], 'document_number' => ['required', 'string']]);

        return new JsonResponse(Json::participant($service->create($data['full_name'], $data['document_number'])), 201);
    }
}
