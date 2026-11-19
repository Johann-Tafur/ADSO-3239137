<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Json;
use App\Application\Results\Photo;
use App\Application\Services\CreateKitService;
use App\Application\Services\KitPhotoService;
use App\Application\Services\RemoveKitService;
use App\Application\Services\SearchKitsService;
use App\Application\Services\UpdateKitService;
use App\Domain\Errors\ValidationError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** HU-03 y HU-04: catálogo de kits, categorías y fotos (api-contract.md §3.3 y §3.4). */
final class KitController extends ApiController
{
    private const KIT_RULES = [
        'name' => ['required', 'string'],
        'manufacturer' => ['required', 'string'],
        'reference' => ['required', 'string'],
        'category_id' => ['required', 'string'],
        'total_units' => ['required', 'integer'],
    ];

    public function categories(SearchKitsService $service): JsonResponse
    {
        return new JsonResponse(['items' => array_map(Json::category(...), $service->categories())]);
    }

    public function index(Request $request, SearchKitsService $service): JsonResponse
    {
        $page = $service->search($this->optionalText($request, 'q'), $this->optionalText($request, 'category_id'), $this->pageRequest($request));

        return new JsonResponse(Json::page($page, Json::kit(...)));
    }

    public function show(string $id, SearchKitsService $service): JsonResponse
    {
        return new JsonResponse(Json::kit($service->get($id)));
    }

    public function store(Request $request, CreateKitService $service): JsonResponse
    {
        $data = $request->validate(self::KIT_RULES);
        $view = $service->create($data['name'], $data['manufacturer'], $data['reference'], $data['category_id'], (int) $data['total_units']);

        return new JsonResponse(Json::kit($view), 201);
    }

    public function update(string $id, Request $request, UpdateKitService $service): JsonResponse
    {
        $data = $request->validate(self::KIT_RULES);
        $view = $service->update($id, $data['name'], $data['manufacturer'], $data['reference'], $data['category_id'], (int) $data['total_units']);

        return new JsonResponse(Json::kit($view));
    }

    public function destroy(string $id, RemoveKitService $service): Response
    {
        $service->remove($id);

        return new Response(status: 204);
    }

    /**
     * PUT /kits/{id}/photo, multipart con el campo `file`. El tipo se lee del CONTENIDO (finfo), no de la extensión
     * ni de lo que diga el navegador (api-contract.md §4). Tamaño y tipo los valida KitPhotoService.
     */
    public function uploadPhoto(string $id, Request $request, KitPhotoService $service, SearchKitsService $kits): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || is_array($file)) {
            throw ValidationError::forField('file', 'obligatorio', 'Adjunta la foto en el campo «file».');
        }
        if (! $file->isValid()) {
            // PHP descarta solo los archivos más grandes que upload_max_filesize
            throw ValidationError::forField('file', 'máximo 2 MB', 'La foto puede pesar máximo 2 MB.');
        }
        // El tipo sale de los BYTES (finfo), nunca del nombre ni de lo que declara el navegador:
        // un «virus.png» que por dentro es PHP se detecta como text/x-php y se rechaza
        $contents = (string) file_get_contents($file->getRealPath());
        $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $service->upload($id, new Photo($contents, $mimeType));

        return new JsonResponse(Json::kit($kits->get($id)));
    }

    public function removePhoto(string $id, KitPhotoService $service): Response
    {
        $service->remove($id);

        return new Response(status: 204);
    }

    public function showPhoto(string $id, KitPhotoService $service): Response
    {
        $photo = $service->read($id);

        return new Response($photo->contents, 200, [
            'Content-Type' => $photo->mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
