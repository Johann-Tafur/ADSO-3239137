# api — Robotics

API REST del préstamo de kits de robótica educativa, en **Laravel 13** sobre **PHP 8.3**. Implementa
[`../docs/api-contract.md`](../docs/api-contract.md) con la arquitectura de [`../docs/architecture.md`](../docs/architecture.md).

## Requisitos

- PHP 8.3 con las extensiones `pdo_pgsql` y `sodium`
- Composer 2
- La base de datos de [`../infra`](../infra) encendida

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

## Pruebas

```bash
php artisan test
```

## Qué se quitó del esqueleto de Laravel y por qué

| Se quitó | Por qué |
|---|---|
| Migraciones `users`, `cache` y `jobs` | El esquema `robotics` tiene solo las seis tablas del modelo de datos ([ADR-001](../docs/adr/adr-001-propiedad-del-esquema.md), hallazgo H-02) |
| `app/Models/User.php`, fábricas y seeders | El dominio no usa Eloquent (artículo I) y la semilla de categorías va en la migración |
| `routes/web.php`, vistas, Vite y `package.json` | Es una API: la interfaz vive en [`../app`](../app) |
| Ruta `/up` y rutas `storage/{path}` | No están en el contrato; la salud es `GET /api/v1/health` |

Sesión, caché y colas usan `array`, `file` y `sync` para que Laravel no necesite tablas propias (plan §2).
