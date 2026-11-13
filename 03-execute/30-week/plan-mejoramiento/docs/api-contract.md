# Contrato de la API — Robotics

Contrato HTTP entre `app` (React), `tool` (sembrador) y `api` (Laravel). Qué hace cada operación está en [`spec.md`](spec.md);
qué se guarda, en [`data-model.md`](data-model.md).

## 1. Convenciones

| Tema | Regla |
|---|---|
| Base | `/api/v1` |
| Formato | JSON, UTF-8. Nombres de campo en `snake_case` |
| Identificadores | UUID v4 como texto |
| Fechas | ISO 8601 en UTC, con `Z` (`2026-11-14T14:15:00Z`) |
| Autenticación | `Authorization: Bearer <token>` en todo salvo `POST /auth/login` y `GET /health` (CA-01.3) |
| Paginación | `?page=1&size=20`. `size` máximo 50. La respuesta es `{ "items": [...], "page": 1, "size": 20, "total": 134 }` |
| Idioma | Mensajes de error en español; campos y códigos en inglés (RN-6) |

### Cuerpo de error (único para todos los errores, RNF-4)

```json
{
  "code": "KIT_NOT_AVAILABLE",
  "message": "No quedan unidades disponibles del kit «Arduino Starter Kit».",
  "details": [ { "field": "items[0].kit_id", "issue": "sin unidades" } ]
}
```

`details` puede ir vacío (`[]`). **Nunca** incluye trazas, SQL, rutas del servidor ni la contraseña.
Los errores del framework (validación, ruta inexistente, método no permitido) también salen con este cuerpo.

### Códigos de estado

| Estado | Cuándo |
|---|---|
| 200 / 201 / 204 | Éxito / creado / sin contenido |
| 401 | Sin token, token inválido o vencido, credenciales inválidas |
| 403 | Rol insuficiente |
| 404 | No existe (o está dado de baja) |
| 409 | Conflicto de estado: duplicado, sin unidades, ya devuelto, participante con vencidos, kit con préstamos abiertos |
| 422 | Validación: campo inválido, regla de entrada rota, rango de fechas invertido |

## 2. Códigos de error de negocio

| `code` | Estado | Origen |
|---|---|---|
| `INVALID_CREDENTIALS` | 401 | CA-01.2 (no dice si falló el usuario o la clave) |
| `UNAUTHENTICATED` | 401 | CA-01.3 (sin token, token inválido o vencido) |
| `FORBIDDEN_ROLE` | 403 | CA-01.3 |
| `NOT_FOUND` | 404 | Cualquier recurso; CA-04.3 |
| `USERNAME_TAKEN` | 409 | CA-02.2 |
| `REFERENCE_TAKEN` | 409 | CA-03.2 |
| `DOCUMENT_TAKEN` | 409 | CA-05.1 |
| `KIT_HAS_OPEN_LOANS` | 409 | CA-03.5 |
| `KIT_NOT_AVAILABLE` | 409 | CA-06.3 y RNF-2 (también cuando pierde la carrera por la última unidad) |
| `PARTICIPANT_HAS_OVERDUE` | 409 | CA-06.5 |
| `ITEM_ALREADY_RETURNED` | 409 | CA-07.2 |
| `PARTICIPANT_LIMIT_EXCEEDED` | 422 | CA-06.4 |
| `DUPLICATE_KIT_IN_LOAN` | 422 | CA-06.2 |
| `TOTAL_BELOW_LOANED` | 422 | CA-03.3 (el nuevo total dejaría disponibles < 0) |
| `ROLE_NOT_ALLOWED` | 422 | CA-02.3 (pedir rol `admin` por la API) |
| `VALIDATION_ERROR` | 422 | Cualquier campo inválido (formato de referencia, foto de más de 2 MB, etc.) |
| `INVALID_DATE_RANGE` | 422 | CA-10.2 |

Para `PARTICIPANT_LIMIT_EXCEEDED` el conteo actual va en el mensaje y en `details`:
`[{ "field": "items", "issue": "sin devolver: 2" }]` (CA-06.4).

## 3. Endpoints

### 3.1 Autenticación y salud

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/auth/login` | público | Devuelve token (HU-01) |
| GET | `/health` | público | `{ "status": "ok" }` |

`POST /auth/login`

```json
// petición
{ "username": "laura", "password": "********" }
// 200
{ "access_token": "eyJ...", "token_type": "Bearer", "expires_in": 28800, "role": "mentor" }
```

### 3.2 Usuarios

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/users` | admin | Crea un mentor (HU-02) |

```json
// petición  (role solo admite "mentor"; "admin" → 422 ROLE_NOT_ALLOWED)
{ "username": "Laura ", "password": "********", "role": "mentor" }
// 201  (sin password ni password_hash)
{ "id": "…", "username": "laura", "role": "mentor" }
```

### 3.3 Categorías

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/categories` | autenticado | Las cinco categorías, orden por nombre |

### 3.4 Kits

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/kits` | autenticado | Búsqueda: `q`, `category_id`, `page`, `size` (HU-04) |
| GET | `/kits/{id}` | autenticado | Detalle; 404 si no existe o está dado de baja |
| POST | `/kits` | admin | Crea (CA-03.1) |
| PUT | `/kits/{id}` | admin | Reemplaza nombre, fabricante, referencia, categoría y total (CA-03.3) |
| DELETE | `/kits/{id}` | admin | Baja lógica (CA-03.5) → 204 |
| PUT | `/kits/{id}/photo` | admin | Sube foto (`multipart/form-data`, campo `file`) (CA-03.4) |
| DELETE | `/kits/{id}/photo` | admin | Quita la foto → 204 |
| GET | `/kits/{id}/photo` | autenticado | Devuelve el binario de la foto o 404 |

Kit (respuesta):

```json
{
  "id": "…", "name": "Arduino Starter Kit", "manufacturer": "Arduino",
  "reference": "ARD-0001", "category": { "id": "…", "name": "Iniciación" },
  "total_units": 4, "available_units": 3, "photo_key": null
}
```

`POST /kits` (petición): `name`, `manufacturer`, `reference`, `category_id`, `total_units`. Las disponibles las calcula el servidor.
`photo_key` es una clave opaca: para mostrar la foto se usa `GET /kits/{id}/photo`.

### 3.5 Participantes

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/participants` | admin, mentor | Lista y busca por nombre o documento: `q`, `page`, `size` (CA-05.2) |
| GET | `/participants/{id}` | admin, mentor | Detalle con `active_items` (líneas sin devolver, la misma cuenta de RN-2) |
| POST | `/participants` | admin, mentor | Crea: `full_name`, `document_number` (CA-05.1) |

No hay `PUT` ni `DELETE`: los participantes no se eliminan (CA-05.3).

### 3.6 Préstamos

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/loans` | admin, mentor | Registra un préstamo (HU-06) |
| GET | `/loans` | admin, mentor | Lista: `from`, `to`, `status`, `participant_id`, `page`, `size` (CA-08.2) |
| GET | `/loans/overdue` | admin, mentor | Líneas vencidas (HU-09) |
| GET | `/loans/{id}` | admin, mentor | Detalle con líneas y estado (CA-08.1) |
| POST | `/loans/{loan_id}/items/{item_id}/return` | admin, mentor | Devuelve una línea (HU-07) |

`/loans/overdue` se declara **antes** de `/loans/{id}` y `{id}` solo acepta formato UUID, para que no choquen en el enrutador.

`POST /loans`

```json
// petición  (1 o 2 elementos, sin kits repetidos)
{ "participant_id": "…", "items": [ { "kit_id": "…" }, { "kit_id": "…" } ] }
// 201
{
  "id": "…", "participant_id": "…", "loaned_by_user_id": "…", "loaned_at": "2026-11-14T14:15:00Z", "status": "open",
  "items": [
    { "id": "…", "kit_id": "…", "kit_name": "Arduino Starter Kit", "kit_reference": "ARD-0001",
      "category_name": "Iniciación", "due_at": "2026-11-26T14:15:00Z", "returned_at": null }
  ]
}
```

`status` es calculado (CA-08.3): `open` (hay líneas sin devolver y ninguna vencida), `overdue` (alguna sin devolver y vencida),
`closed` (todas devueltas).

`POST /loans/{loan_id}/items/{item_id}/return` → 200 con la línea (`returned_at` lleno). Segunda vez → 409 `ITEM_ALREADY_RETURNED`.

`GET /loans/overdue` → `{ "items": [ { "loan_id", "item_id", "kit_name", "kit_reference", "participant_name", "due_at", "days_late" } ] }`,
orden por `days_late` descendente. Aquí sí se muestra el nombre del participante porque es un listado operativo; **el reporte no**.

### 3.7 Reporte

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/reports/most-requested` | admin | `from` y `to` obligatorios (fecha `AAAA-MM-DD`, ambos inclusive) (HU-10) |

```json
{
  "from": "2026-11-01", "to": "2026-11-30",
  "rows": [
    { "kit_id": "…", "kit_name": "Arduino Starter Kit", "category_name": "Iniciación", "times_requested": 12 }
  ]
}
```

Una fila por `kit_id + kit_name + category_name` **congelados** en la línea. Orden: `times_requested` descendente, luego `kit_name`.
Sin participantes ni mentores. El rango se aplica como `loaned_at >= from 00:00:00Z` y `loaned_at < (to + 1 día) 00:00:00Z`.

## 4. Seguridad

| Aspecto | Regla |
|---|---|
| Token | JWT HS256, 8 horas, con `sub` (id del usuario) y `role`. Sin datos personales dentro |
| Contraseñas | Solo se guarda el hash Argon2id; ni la clave ni el hash salen en una respuesta ni en un log (RNF-3) |
| Autorización | Se evalúa en cada endpoint por rol; un 403 no revela si el recurso existe |
| CORS | Solo el origen del frontend configurado por entorno (`CORS_ORIGIN`) |
| Subida de foto | Solo `image/jpeg` y `image/png`, máx. 2 MB; el tipo se valida por contenido, no por extensión |
