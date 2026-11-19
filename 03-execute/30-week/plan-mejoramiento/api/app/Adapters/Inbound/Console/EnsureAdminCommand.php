<?php

namespace App\Adapters\Inbound\Console;

use App\Application\Services\EnsureAdminService;
use App\Domain\Errors\DomainError;
use Illuminate\Console\Command;
use LogicException;

/**
 * `php artisan robotics:ensure-admin` (plan D-10). Adaptador de entrada por consola: lee ADMIN_USERNAME y
 * ADMIN_PASSWORD del entorno y llama al caso de uso. El arranque del contenedor lo corre después de `migrate` (T-20).
 * Nunca imprime la clave.
 */
final class EnsureAdminCommand extends Command
{
    protected $signature = 'robotics:ensure-admin';

    protected $description = 'Crea el admin inicial con ADMIN_USERNAME y ADMIN_PASSWORD si todavía no existe';

    public function handle(EnsureAdminService $service): int
    {
        $username = (string) config('robotics.admin_username');
        $password = (string) config('robotics.admin_password');
        if (trim($username) === '' || $password === '') {
            $this->error('Faltan ADMIN_USERNAME o ADMIN_PASSWORD en el entorno.');

            return self::FAILURE;
        }

        try {
            $created = $service->ensure($username, $password);
        } catch (DomainError|LogicException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info($created ? 'Admin creado.' : 'El admin ya existía; no se cambió nada.');

        return self::SUCCESS;
    }
}
