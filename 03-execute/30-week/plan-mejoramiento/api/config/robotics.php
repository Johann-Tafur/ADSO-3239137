<?php

/**
 * Configuración propia de Robotics (plan.md §2). Todo sale del entorno; el .env real nunca se sube.
 */
return [
    // Firma de los tokens (HS256, mínimo 32 caracteres). JwtTokenIssuer se niega a arrancar sin ella.
    'jwt_secret' => env('JWT_SECRET', ''),

    // Carpeta de las fotos de kits (plan D-08)
    'photos_dir' => env('PHOTOS_DIR', storage_path('app/photos')),

    // Admin inicial que crea `php artisan robotics:ensure-admin` (plan D-10, T-17)
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
];
