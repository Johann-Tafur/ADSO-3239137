# Infra — Robotics

Levanta el sistema con un solo comando. La base de datos arranca **vacía**: el esquema lo crean las migraciones de `api` (artículo V).

## Uso

```bash
cp .env.example .env   # y cambia la clave
docker compose up -d
```

## Servicios

| Servicio | Qué es | Puerto |
|---|---|---|
| `db` | PostgreSQL 16, vacío, en UTC | 5432 |

Los servicios `api` y `app` se agregan en la tarea T-20.

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
