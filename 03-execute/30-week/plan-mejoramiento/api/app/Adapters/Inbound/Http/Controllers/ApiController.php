<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Middleware\AuthenticateWithToken;
use App\Application\Paging\PageRequest;
use App\Application\Results\AccessClaims;
use Illuminate\Http\Request;

/**
 * Base de los controladores. Un controlador es delgado: valida la FORMA de la entrada (tipos, campos presentes),
 * llama a un caso de uso y convierte el resultado con Json. Las reglas del negocio no viven aquí.
 */
abstract class ApiController
{
    protected function claims(Request $request): AccessClaims
    {
        return $request->attributes->get(AuthenticateWithToken::CLAIMS);
    }

    /** `?page=1&size=20`; size máximo 50 lo valida PageRequest (api-contract.md §1). */
    protected function pageRequest(Request $request): PageRequest
    {
        $request->validate(['page' => ['sometimes', 'integer'], 'size' => ['sometimes', 'integer']]);

        return new PageRequest((int) $request->query('page', 1), (int) $request->query('size', PageRequest::DEFAULT_SIZE));
    }

    protected function optionalText(Request $request, string $field): ?string
    {
        $value = $request->query($field);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
