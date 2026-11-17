# Plan técnico — Robotics (Laravel + React)

Cómo se construye lo que pide [`spec.md`](spec.md). El negocio no se decide aquí: aquí solo se eligen las herramientas
y se deja escrito por qué.

## 1. Decisiones técnicas

| # | Decisión | Elección | Por qué |
|---|---|---|---|
| D-01 | Lenguaje del backend | **PHP 8.3** | Stack del plan de mejoramiento |
| D-02 | Framework HTTP | **Laravel**: rutas en `routes/api.php`, controladores como adaptador de entrada | Cumple [`api-contract.md`](api-contract.md) sin que el dominio dependa del framework (artículo I) |
| D-03 | Persistencia | **Migraciones de Laravel** sobre **PostgreSQL 16**, esquema `robotics`; repositorios con el Query Builder (sin Eloquent en el dominio) | [ADR-001](adr/adr-001-propiedad-del-esquema.md): el esquema es de las migraciones |
| D-04 | Concurrencia | Columna `kit.version` + `UPDATE … WHERE id = ? AND version = ?`; si afecta 0 filas → 409 `KIT_NOT_AVAILABLE` al prestar y `KIT_CHANGED` en las demás escrituras, **sin reintento**. El límite por participante lo protege el bloqueo de su fila ([ADR-004](adr/adr-004-bloqueo-del-participante.md)). Última barrera: `ck_kit_available_range` | [ADR-002](adr/adr-002-concurrencia-optimista.md), RNF-2 |
| D-05 | Fechas | `timestamptz` y puerto `Clock`; en pruebas un `FixedClock` | Probar vencimientos sin esperar 12 días (RN-6) |
| D-06 | Reporte | Puerto `ReportReader`, una sola consulta sobre `loan_item` unida con `loan` (que tiene `loaned_at`, filtrado con el índice `ix_loan_loaned_at`), `GROUP BY kit_id, kit_name, category_name` | Lee lo congelado, no el catálogo vivo (artículo VIII) |
| D-07 | Objetos de valor | Clases PHP `final readonly` en `app/Domain/ValueObjects`: `KitReference`, `Units`, `DocumentNumber`, `DateRange` | No tienen identidad, no tienen tabla |
| D-08 | Fotos | Puerto `PhotoStorage`; adaptador `LocalDiskPhotoStorage` (`storage/app/photos`). Cambiar a la nube = una línea en la composición | OCP (artículo IV) |
| D-09 | Contraseñas | `password_hash(..., PASSWORD_ARGON2ID)` detrás del puerto `PasswordHasher` | Artículo IX, RNF-3 |
| D-10 | Admin inicial | Comando `php artisan robotics:ensure-admin` que lee `ADMIN_USERNAME`/`ADMIN_PASSWORD`; lo corre el arranque del contenedor después de `migrate` | La base no puede producir el hash; nadie otorga `admin` en ejecución |
| D-11 | Autenticación | `firebase/php-jwt`, HS256, 8 h, claims `sub` y `role`; middleware propio. **No** se usa Sanctum (crea la tabla `personal_access_tokens`) | Sin estado y sin séptima tabla |
| D-12 | Pruebas | **PHPUnit** con fakes en memoria para los puertos; PostgreSQL real solo en integración | Pirámide de pruebas (§3) |
| D-13 | Frontend | **React 18 + Vite + react-router**; cliente HTTP con `fetch` | Stack del plan |
| D-14 | Sitio `page` | HTML + CSS estáticos, sin build ni API | Debe abrirse sin el backend (T-23) |
| D-15 | Sembrador `tool` | Script Node (`fetch`) que solo llama a la API pública | Data-model §9: la semilla de demostración nunca usa SQL |

## 2. Configuración por entorno (`.env`, solo se versiona `.env.example`)

| Variable | Obligatoria | Ejemplo |
|---|---|---|
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | sí | `pgsql` / `db` / `5432` / `robotics` / `robotics` / *(secreto)* |
| `DB_SCHEMA` | sí | `robotics` |
| `JWT_SECRET` | sí | cadena larga aleatoria |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | sí | definidos en `.env`, nunca en el repo |
| `PHOTOS_DIR` | no | `storage/app/photos` |
| `CORS_ORIGIN` | sí | `http://localhost:5173` |

`SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` se fijan en `array`/`file`/`sync` para que Laravel **no** cree tablas propias.

## 3. Estrategia de pruebas

Meta: **≥ 15 archivos de pruebas automatizadas**, todas en `api/tests` y ejecutables con `php artisan test`.

| Nivel | Qué prueba | Dónde |
|---|---|---|
| Dominio | Máximo 2, vencimiento a 12 días, disponibles en rango, referencia `AAA-0000` | `tests/Unit/Domain` |
| Aplicación | Login, kits, prestar, devolver, vencidos, reporte, contra fakes | `tests/Unit/Application` |
| Arquitectura | Artículos I, II y III | `tests/Architecture` |
| Contrato | Cuerpo de error único, códigos de §2 del contrato, roles | `tests/Feature` |
| Integración | Repositorios y concurrencia contra PostgreSQL real | `tests/Integration` |

## 4. Orden de construcción

Esqueleto y salud → migración inicial con semilla → dominio → casos de uso con fakes → adaptadores → rutas HTTP →
pruebas de contrato y concurrencia → infra completa → app → tool → page. El detalle y la evidencia de cada paso están en [`tasks.md`](tasks.md).

## 5. Hallazgos al leer el spec y cómo se resolvieron

| # | Hallazgo | Decisión |
|---|---|---|
| H-01 | RNF-2 exige concurrencia optimista, pero el kit necesita dónde guardar el testigo | `kit.version integer not null`, sin `DEFAULT`: la pone el dominio (data-model §3) |
| H-02 | Laravel trae tablas por defecto (`users`, `sessions`, `cache`, `jobs`, `password_reset_tokens`) y columnas con `DEFAULT` | Se eliminan esas migraciones. La tabla es `user` (singular), no `users` |
| H-03 | La tabla de control `migrations` de Laravel sería una séptima tabla | Vive en el esquema `public`; `robotics` queda con exactamente seis |
| H-04 | `GET /loans/overdue` y `GET /loans/{id}` chocan en el enrutador | `overdue` se declara primero y `{id}` se restringe a UUID |
| H-05 | CA-06.4 pide «422 con el conteo actual» sin decir dónde | El conteo va en `message` y en `details` (api-contract §2) |
| H-06 | El reporte recibe fechas, pero `loaned_at` es un instante | `loaned_at >= from 00:00Z` y `< (to + 1 día) 00:00Z` |
| H-07 | Los vencidos no se pueden sembrar por la API: el servidor fija `loaned_at` con su reloj | El sembrador crea préstamos vigentes; los vencidos se prueban con `FixedClock` en `tests/Unit` |
