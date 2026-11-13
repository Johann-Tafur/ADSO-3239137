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
