<?php

namespace App\Adapters\Inbound\Http;

use App\Domain\Errors\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * El ÚNICO lugar que convierte errores en respuestas HTTP (architecture.md §5, RNF-4).
 * Todo error sale con el mismo cuerpo: { "code", "message", "details" } (api-contract.md §1).
 * Nunca incluye trazas, SQL, rutas del servidor ni la contraseña.
 */
final class ApiErrorRenderer
{
    /** Estado HTTP de cada código de negocio (api-contract.md §2). Lo que no está aquí es 422. */
    private const STATUS = [
        'INVALID_CREDENTIALS' => 401,
        'UNAUTHENTICATED' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'REFERENCE_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'KIT_HAS_OPEN_LOANS' => 409,
        'KIT_NOT_AVAILABLE' => 409,
        'KIT_CHANGED' => 409,
        'PARTICIPANT_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'PARTICIPANT_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_KIT_IN_LOAN' => 422,
        'TOTAL_BELOW_LOANED' => 422,
        'ROLE_NOT_ALLOWED' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    /** Cómo se dice en español cada regla de Laravel que puede fallar en la entrada. */
    private const ISSUES = [
        'Required' => 'obligatorio',
        'RequiredWith' => 'obligatorio junto con el otro extremo del rango',
        'String' => 'debe ser texto',
        'Integer' => 'debe ser un número entero',
        'Array' => 'debe ser una lista',
        'Uuid' => 'debe ser un UUID',
        'File' => 'debe ser un archivo',
        'Min' => 'valor demasiado pequeño',
        'Max' => 'valor demasiado grande',
    ];

    public static function render(Throwable $error): JsonResponse
    {
        return match (true) {
            $error instanceof DomainError => self::response(
                $error->errorCode(), $error->getMessage(), self::STATUS[$error->errorCode()] ?? 422, $error->details(),
            ),
            $error instanceof ValidationException => self::response(
                'VALIDATION_ERROR', 'Hay datos inválidos en la petición.', 422, self::validationDetails($error),
            ),
            $error instanceof NotFoundHttpException, $error instanceof MethodNotAllowedHttpException => self::response(
                'NOT_FOUND', 'La ruta no existe.', 404,
            ),
            $error instanceof HttpExceptionInterface && $error->getStatusCode() === 413 => self::response(
                'VALIDATION_ERROR', 'La petición es demasiado grande.', 422, [['field' => 'file', 'issue' => 'máximo 2 MB']],
            ),
            default => self::response('INTERNAL_ERROR', 'Ocurrió un error interno. Intenta de nuevo.', 500),
        };
    }

    /** @param list<array{field: string, issue: string}> $details */
    public static function response(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message, 'details' => $details], $status);
    }

    public static function statusOf(string $code): ?int
    {
        return self::STATUS[$code] ?? null;
    }

    /** @return list<array{field: string, issue: string}> */
    private static function validationDetails(ValidationException $error): array
    {
        $details = [];
        foreach ($error->validator->failed() as $field => $rules) {
            $rule = array_key_first($rules);
            $details[] = ['field' => $field, 'issue' => self::ISSUES[$rule] ?? 'inválido'];
        }

        return $details;
    }
}
