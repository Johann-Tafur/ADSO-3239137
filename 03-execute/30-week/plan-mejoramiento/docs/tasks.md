# Tareas — Robotics

Cada tarea termina cuando su **evidencia** existe y se puede ejecutar (artículo XII). El estado se actualiza en el mismo commit
que aporta la evidencia.

**Cómo correr la evidencia** (`api/`): `php artisan test`.

| # | Tarea | Depende de | Evidencia | Estado |
|---|---|---|---|---|
| T-01 | Esqueleto de Laravel y `GET /api/v1/health` | — | Responde `{"status":"ok"}` · `tests/Feature/HealthTest.php` | pendiente |
| T-02 | Migración inicial: seis tablas, FK, índices únicos y las cinco categorías | T-01 | Las consultas de `data-model.md` §10 devuelven lo documentado; ninguna columna con `DEFAULT` | pendiente |
| T-03 | Dominio: `KitReference`, `Units`, `DocumentNumber`, `DateRange` | T-01 | `tests/Unit/Domain/ValueObjectsTest.php`: `ARD-0001` válido, `ard-1` no; unidades ≥ 1; fin ≥ inicio | pendiente |
| T-04 | Dominio: `Kit` (`withdraw`, `restock`, cambio de total, baja lógica) | T-03 | `tests/Unit/Domain/KitTest.php`: nunca < 0, nunca > total | pendiente |
| T-05 | Dominio: `Loan` (`addItem`, `returnItem`, estado calculado, 12 días, máx. 2 líneas) | T-04 | `tests/Unit/Domain/LoanTest.php` con `FixedClock`: `open`, `overdue`, `closed` | pendiente |
| T-06 | Dominio: `Participant` y `User` | T-03 | `tests/Unit/Domain/ParticipantAndUserTest.php`: `"Laura "` queda `laura`; rol `admin` por API rechazado | pendiente |
| T-07 | Casos de uso de **autenticación** (`LoginService`, `CreateMentorService`) con fakes | T-06 | HU-01 y HU-02 pasan contra fakes | pendiente |
| T-08 | Casos de uso de **kits** (crear, buscar, editar, baja, foto) con fakes | T-04 | HU-03 y HU-04 pasan contra fakes | pendiente |
| T-09 | Caso de uso **prestar** (`LendKitsService`) con fakes | T-05, T-08 | HU-06 completa: máximo 2, vencidos, sin unidades | pendiente |
| T-10 | Casos de uso **devolver** y **vencidos** | T-09 | HU-07 y HU-09; devolver dos veces → `ITEM_ALREADY_RETURNED` | pendiente |
| T-11 | Caso de uso **reporte de kits más solicitados** y su puerto de lectura | T-09 | HU-10; el nombre viejo se conserva tras renombrar el kit (artículo VIII) | pendiente |
| T-12 | Persistencia: repositorios de kit, participante, usuario y categoría | T-02, T-08 | `tests/Integration` contra PostgreSQL real | pendiente |
| T-13 | Persistencia: repositorio de préstamos + testigo `version` | T-12, T-09 | Dos préstamos de la última unidad: uno 201, otro 409 (RNF-2) | pendiente |
| T-14 | Seguridad: hash Argon2id y token JWT | T-07 | La clave nunca aparece en logs ni respuestas (RNF-3) | pendiente |
| T-15 | Fotos en disco (`LocalDiskPhotoStorage`) | T-08 | PNG de 2 MB aceptado; 3 MB → 422; sin foto → `photo_key` nulo | pendiente |
| T-16 | HTTP: rutas de [`api-contract.md`](api-contract.md), cuerpo de error único, autorización por rol | T-10, T-11, T-14, T-15 | `tests/Feature` recorre todas las rutas y los códigos de §2 | pendiente |
| T-17 | Composición y arranque (admin inicial desde entorno) | T-16 | Con la base vacía, tras `migrate` y `robotics:ensure-admin`, se inicia sesión como admin | pendiente |
| T-18 | Bajar a la base `ck_kit_total_positive`, `ck_user_role` y `ck_kit_reference_format` | T-12 | Un `INSERT` por `psql` que las viole falla; `data-model.md` §4 se actualiza | pendiente |
| T-19 | Pruebas de arquitectura (artículos I, II y III) | T-17 | `tests/Architecture/LayerDependenciesTest.php` pasa, y falla si se introduce una importación prohibida | pendiente |
| T-20 | `infra`: `docker compose up` levanta base vacía, API y app | T-17 | Un comando; la base arranca sin tablas hasta que corren las migraciones | pendiente |
| T-21 | `tool`: sembrador de demostración **por la API** (≥ 40 kits, 10 participantes, 15 préstamos) | T-17 | Tras correrlo, `/kits` devuelve ≥ 40 y el reporte tiene filas; `--kits 5000` sirve para medir RNF-1 | pendiente |
| T-22 | `app`: login, kits, participantes, prestar, devolver, vencidos, reporte, mentores | T-16 | Recorrido manual de punta a punta con los dos roles | pendiente |
| T-23 | `page`: sitio público estático que presenta el producto | — | Se abre sin la API; se ve bien en móvil y escritorio | pendiente |

## Trazabilidad

| Historia / requisito | Tareas |
|---|---|
| HU-01, HU-02 | T-06, T-07, T-14, T-16, T-17 |
| HU-03, HU-04 | T-03, T-04, T-08, T-12, T-15, T-16 |
| HU-05 | T-06, T-12, T-16 |
| HU-06 | T-05, T-09, T-13, T-16 |
| HU-07, HU-08, HU-09 | T-05, T-10, T-13, T-16 |
| HU-10 | T-11, T-16 |
| RNF-1 | T-12, T-21 |
| RNF-2 | T-13 |
| RNF-3 | T-14 |
| RNF-4 | T-16 |
| RNF-5 | T-20 |

## Definición de terminado

Con T-17 hecha y su evidencia en verde, el **núcleo** está listo. T-18 a T-23 completan el producto. Todas son obligatorias en este
plan de mejoramiento.
