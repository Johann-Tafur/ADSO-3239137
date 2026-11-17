# Tareas — Robotics

Cada tarea termina cuando su **evidencia** existe y se puede ejecutar (artículo XII). El estado se actualiza en el mismo commit
que aporta la evidencia.

## Cómo correr la evidencia

| Tipo de evidencia | Tareas | Cómo se ejecuta |
|---|---|---|
| Pruebas automatizadas | T-01, T-03 a T-17, T-19 | En `api/`: `php artisan test` (las de `tests/Integration` necesitan la base de `infra` encendida) |
| Consultas SQL | T-02, T-18 | Con la base de `infra` encendida: `docker compose exec db psql -U robotics -d robotics` y las consultas de `data-model.md` §10 |
| Contenedores | T-20 | En `infra/`: `docker compose up` |
| Sembrador | T-21 | En `tool/`: el comando que documenta `tool/README.md` |
| Guion de recorrido | T-22, T-23 | Los pasos y resultados esperados escritos en `app/README.md` y `page/README.md`; se repiten tal cual |

La base de datos existe desde el día 1: `infra/compose.yaml` levanta PostgreSQL 16 vacío. T-20 lo completa con la API y la app.

## Lista de tareas

| # | Tarea | Depende de | Evidencia | Estado |
|---|---|---|---|---|
| T-01 | Esqueleto de Laravel conectado al PostgreSQL de `infra` y `GET /api/v1/health` | — | Responde `{"status":"ok"}` · `tests/Feature/HealthTest.php`; `php artisan migrate:status` se conecta a la base | **hecha** |
| T-02 | Migración inicial: seis tablas, FK, índices únicos y las cinco categorías | T-01 | Las consultas de `data-model.md` §10 devuelven lo documentado; ninguna columna con `DEFAULT` · `tests/Integration/SchemaTest.php` | **hecha** |
| T-03 | Dominio: `KitReference`, `Units`, `DocumentNumber`, `DateRange` | T-01 | `tests/Unit/Domain/ValueObjectsTest.php`: `ARD-0001` válido, `ard-1` no; unidades ≥ 1; fin ≥ inicio | **hecha** |
| T-04 | Dominio: `Kit` (`withdraw`, `restock`, cambio de total, baja lógica) | T-03 | `tests/Unit/Domain/KitTest.php`: nunca < 0, nunca > total | **hecha** |
| T-05 | Dominio: `Loan` (`lend`, `returnItem`, estado calculado, 12 días, máx. 2 líneas) | T-04 | `tests/Unit/Domain/LoanTest.php` con `FixedClock`: `open`, `overdue`, `closed` | **hecha** |
| T-06 | Dominio: `Participant`, `User` y `Role` | T-03 | `tests/Unit/Domain/ParticipantAndUserTest.php`: el usuario `"Laura "` queda `laura`; el nombre del participante se recorta; `Role` acepta `admin` y `mentor` y rechaza cualquier otro rol | **hecha** |
| T-07 | Casos de uso de **autenticación** (`LoginService`, `CreateMentorService`) con fakes | T-06 | HU-01 y HU-02 pasan contra fakes; pedir el rol `admin` → `ROLE_NOT_ALLOWED` (CA-02.3) · `tests/Unit/Application/LoginServiceTest.php` y `CreateMentorServiceTest.php` | **hecha** |
| T-08 | Casos de uso de **kits** (crear, buscar, editar, baja, foto) y de **participantes** (`CreateParticipantService`, `SearchParticipantsService`) con fakes | T-04, T-06 | HU-03, HU-04 y HU-05 pasan contra fakes; documento repetido → `DOCUMENT_TAKEN`; búsqueda de participantes por nombre o documento, paginada · `tests/Unit/Application/`: `CreateKitServiceTest`, `UpdateKitServiceTest`, `RemoveKitServiceTest`, `SearchKitsServiceTest`, `KitPhotoServiceTest`, `ParticipantServicesTest` | **hecha** |
| T-09 | Caso de uso **prestar** (`LendKitsService`) con fakes | T-05, T-08 | HU-06 completa: máximo 2, vencidos, sin unidades · `tests/Unit/Application/LendKitsServiceTest.php` | **hecha** |
| T-10 | Casos de uso **devolver**, **consultar préstamos** (`GetLoanService`, `ListLoansService`) y **vencidos** | T-09 | HU-07, HU-08 y HU-09 contra fakes; devolver dos veces → `ITEM_ALREADY_RETURNED`; un préstamo trae sus líneas y su estado; el listado filtra por rango, estado y participante, en orden descendente y paginado · `tests/Unit/Application/ReturnItemServiceTest.php` y `LoanQueriesTest.php` | **hecha** |
| T-11 | Caso de uso **reporte de kits más solicitados** y su puerto de lectura | T-09, T-10 | HU-10; el nombre viejo se conserva tras renombrar el kit (artículo VIII) · `tests/Unit/Application/MostRequestedReportServiceTest.php` | **hecha** |
| T-12 | Persistencia: repositorios de kit, participante, usuario y categoría | T-02, T-08 | `tests/Integration` contra PostgreSQL real; la búsqueda de kits usa los índices de `data-model.md` | pendiente |
| T-13 | Persistencia: repositorio de préstamos + testigo `version` | T-12, T-10 | `tests/Integration`: dos `LendKitsService` sobre la última unidad con la misma `version` leída → uno guarda y el otro recibe `KIT_NOT_AVAILABLE`; `kit.version` sube en 1 (RNF-2) | pendiente |
| T-14 | Seguridad: hash Argon2id y token JWT | T-07 | El hash verifica la clave correcta y rechaza otra; el token dura 8 h y trae `sub` y `role`; la clave nunca aparece en logs (RNF-3) | pendiente |
| T-15 | Fotos en disco (`LocalDiskPhotoStorage`) | T-08 | PNG de 2 MB aceptado; 3 MB → 422; sin foto → `photo_key` nulo | pendiente |
| T-16 | HTTP: rutas de [`api-contract.md`](api-contract.md), cuerpo de error único, autorización por rol | T-11, T-13, T-14, T-15 | `tests/Feature` recorre todas las rutas y los códigos de §2 con los adaptadores reales: la última unidad → 409 `KIT_NOT_AVAILABLE`; ninguna respuesta trae `password` ni `password_hash` (RNF-3) | pendiente |
| T-17 | Composición y arranque (admin inicial desde entorno) | T-16 | Con la base vacía, tras `migrate` y `robotics:ensure-admin`, se inicia sesión como admin | pendiente |
| T-18 | Bajar a la base `ck_kit_total_positive`, `ck_user_role` y `ck_kit_reference_format` | T-12 | Un `INSERT` por `psql` que las viole falla; `data-model.md` §4 se actualiza | pendiente |
| T-19 | Pruebas de arquitectura (artículos I, II y III) | T-01 | `tests/Architecture/LayerDependenciesTest.php` pasa, y falla si se introduce una importación prohibida. Se escribe al empezar el dominio y corre con cada tarea | **hecha** |
| T-20 | `infra`: `docker compose up` levanta base vacía, API y app | T-17 | Un comando; la base arranca sin tablas hasta que corren las migraciones | pendiente |
| T-21 | `tool`: sembrador de demostración **por la API** (≥ 40 kits, 10 participantes, 15 préstamos) | T-17 | Tras correrlo, `/kits` devuelve ≥ 40 y el reporte tiene filas; con `--kits 5000` mide `GET /kits` con búsqueda e imprime el tiempo, y termina con error si pasa de 300 ms (RNF-1) | pendiente |
| T-22 | `app`: login, kits, participantes, prestar, devolver, vencidos, reporte, mentores | T-16 | Guion de recorrido en `app/README.md` con los dos roles: cada paso con su resultado esperado, repetido de punta a punta | pendiente |
| T-23 | `page`: sitio público estático que presenta el producto | — | Guion en `page/README.md`: se abre sin la API; se ve bien en móvil y escritorio | pendiente |

## Trazabilidad

| Historia / requisito | Tareas |
|---|---|
| HU-01, HU-02 | T-06, T-07, T-14, T-16, T-17 |
| HU-03, HU-04 | T-03, T-04, T-08, T-12, T-15, T-16 |
| HU-05 | T-06, T-08, T-12, T-16 |
| HU-06 | T-05, T-09, T-13, T-16 |
| HU-07, HU-09 | T-05, T-10, T-13, T-16 |
| HU-08 | T-05, T-10, T-13, T-16 |
| HU-10 | T-11, T-16 |
| RNF-1 | T-12, T-21 |
| RNF-2 | T-13, T-16 |
| RNF-3 | T-14, T-16 |
| RNF-4 | T-16 |
| RNF-5 | T-20 |

## Definición de terminado

Con T-17 hecha y su evidencia en verde, el **núcleo** está listo: T-16 depende de los repositorios reales (T-12 y T-13), así que
el núcleo no se puede cerrar con fakes. T-19 vigila la arquitectura desde el principio. T-18 y T-20 a T-23 completan el producto.
Todas son obligatorias en este plan de mejoramiento.
