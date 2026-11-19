# Infra — Robotics

Levanta el sistema con un solo comando. La base de datos arranca **vacía**: el esquema lo crean las migraciones de `api` (artículo V).

## Uso

```bash
cp .env.example .env   # y completa las claves (el archivo explica cómo generarlas)
docker compose up      # base, API y app (T-20)
```

Abre la app en `http://localhost:5173` y entra con `ADMIN_USERNAME` / `ADMIN_PASSWORD` del `.env`.

Al arrancar, la API espera a que la base esté lista, corre `migrate` (crea el esquema si está vacía) y
`robotics:ensure-admin` (crea el admin si no existe). La base nace **sin tablas** hasta ese momento.

Para desarrollar con `php artisan serve` y `npm run dev` basta la base:

```bash
docker compose up -d db
```

## Servicios

| Servicio | Qué es | Puerto | Imagen |
|---|---|---|---|
| `db` | PostgreSQL 16, vacío, en UTC | 5432 | `postgres:16-alpine` |
| `api` | Laravel con PHP 8.3 | `API_PORT`, por defecto 8000 | [`api.Dockerfile`](api.Dockerfile) |
| `app` | React compilada, servida por nginx | `APP_PORT`, por defecto 5173 | [`app.Dockerfile`](app.Dockerfile) |

Los `Dockerfile` viven aquí porque son parte del despliegue; cada uno tiene su `.dockerignore` al lado (los secretos y
las dependencias locales nunca entran a una imagen). Las fotos de kits se guardan en el volumen `photos`.

## Bases de datos

El contenedor `db` tiene dos bases, las dos vacías al nacer:

| Base | Para qué |
|---|---|
| `robotics` | La del sistema: la usan la API, la app y el sembrador |
| `robotics_test` | Solo para las pruebas de integración de `api` (`tests/Integration`). Cada prueba se deshace al terminar, así que nunca toca los datos de `robotics` |

`robotics_test` la crea [`initdb/01-test-database.sql`](initdb/01-test-database.sql). PostgreSQL corre esa carpeta **una sola
vez**, cuando crea el volumen. Si el volumen ya existía de antes, créala a mano una vez:

```bash
docker compose exec db createdb -U robotics robotics_test
```

## Apagar

```bash
docker compose stop      # apaga y conserva los datos
```

`docker compose down -v` **borra el volumen** y con él todos los datos: no lo uses salvo que quieras empezar de cero.
