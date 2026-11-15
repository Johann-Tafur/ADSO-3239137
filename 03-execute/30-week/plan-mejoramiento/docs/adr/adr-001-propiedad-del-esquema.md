# ADR-001: El esquema de la base es propiedad de las migraciones

- **Estado:** aceptado
- **Fecha:** 2026-11-15
- **Relacionado con:** [constitución, artículo V](../constitution.md), [plan D-03, H-02 y H-03](../plan.md), [modelo de datos](../data-model.md)

## Contexto

`infra/compose.yaml` levanta un PostgreSQL 16 **vacío**. Alguien tiene que crear las seis tablas del esquema `robotics`
(`category`, `kit`, `participant`, `loan`, `loan_item`, `user`), sus índices y restricciones, y sembrar las cinco categorías.

Hay tres lugares posibles para hacerlo: un script SQL que el contenedor ejecute al arrancar, comandos a mano en `psql` o las
migraciones de Laravel. Si se usa más de uno, la base de un computador puede terminar distinta a la de otro.

Además, Laravel trae migraciones de fábrica (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`) que crean tablas que
el modelo de datos no tiene, y su propia tabla de control, `migrations`, sería una séptima tabla en `robotics`.

## Decisión

1. **Todo el DDL vive en `api/database/migrations`**: tablas, llaves foráneas, índices, restricciones `CHECK` y la semilla de
   las cinco categorías. Es la única fuente de verdad del esquema.
2. `infra` **no** contiene ningún `CREATE TABLE`: solo entrega la base vacía.
3. Se **eliminan las migraciones de fábrica** de Laravel, y `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` usan
   `array`, `file` y `sync` para que Laravel no necesite tablas propias.
4. La tabla de control `migrations` se queda en el esquema `public`. Así `robotics` tiene **exactamente seis tablas**.
5. Una migración que ya corrió **no se edita**: cualquier cambio es una migración nueva.

## Alternativas consideradas

| Alternativa | Por qué se descarta |
|---|---|
| Script `init.sql` montado en el contenedor de PostgreSQL | Solo corre la primera vez que se crea el volumen. Si el esquema cambia, una base ya creada no se entera, y quedan dos fuentes de verdad (el script y el código de la API) |
| Crear las tablas a mano con `psql` | No queda registrado en el repositorio: otra persona que clone el proyecto no obtiene la misma base |
| Dejar las migraciones de fábrica de Laravel | Crean tablas que el spec no pide (informe 6, extras fuera del spec) y rompen la regla de seis tablas |

## Consecuencias

**A favor**
- Con `php artisan migrate` cualquier persona obtiene la misma base, desde cero, en cualquier máquina.
- El historial de cambios del esquema queda en Git, junto al código que lo usa.
- Se puede comprobar: `infra` no tiene `CREATE TABLE` y las consultas de `data-model.md` §10 muestran seis tablas y cinco
  categorías.

**En contra**
- Para cambiar una tabla hay que escribir una migración nueva; no basta con editar la vieja.
- La base no tiene tablas hasta que corren las migraciones, así que el arranque de la API debe ejecutar `migrate` antes de
  atender peticiones (T-17, T-20).
