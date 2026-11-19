<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Json;
use App\Application\Services\CreateMentorService;
use App\Application\Services\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HU-01 y HU-02: iniciar sesión y dar de alta mentores (api-contract.md §3.1 y §3.2). */
final class AuthController extends ApiController
{
    public function login(Request $request, LoginService $service): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);

        $token = $service->login($data['username'], $data['password']);

        return new JsonResponse([
            'access_token' => $token->accessToken,
            'token_type' => $token->tokenType,
            'expires_in' => $token->expiresIn,
            'role' => $token->role->value,
        ]);
    }

    public function createMentor(Request $request, CreateMentorService $service): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string'],
        ]);

        $user = $service->create($data['username'], $data['password'], $data['role']);

        return new JsonResponse(Json::user($user), 201);
    }
}
