# App — Robotics

Aplicación web para el administrador y los mentores: React 18 con Vite, sin otras librerías. Habla solo con la API
(`src/api/client.js`); las pantallas ocultan lo que el rol no puede hacer, pero **la autorización real la hace la API** (403).

## Encender

Con la API encendida en `http://localhost:8000`:

```bash
npm install
npm run dev          # http://localhost:5173
```

Si la API está en otra dirección, copia `.env.example` como `.env` y cambia `VITE_API_URL`.
Con Docker: `docker compose up` en `infra/` levanta todo y la app queda en `http://localhost:5173`.

> En Windows, si PowerShell dice que «la ejecución de scripts está deshabilitada», corre una vez
> `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` y abre otra terminal.

## Pantallas

| Pantalla | Ruta | admin | mentor |
|---|---|---|---|
| Iniciar sesión | — | ✓ | ✓ |
| Kits (buscar) | `#/kits` | ✓ | ✓ |
| Kits (crear, editar, baja, foto) | `#/kits` | ✓ | — |
| Participantes | `#/participants` | ✓ | ✓ |
| Prestar | `#/new-loan` | ✓ | ✓ |
| Préstamos y devolución | `#/loans` | ✓ | ✓ |
| Vencidos | `#/overdue` | ✓ | ✓ |
| Reporte | `#/report` | ✓ | — |
| Mentores | `#/users` | ✓ | — |

## Guion de recorrido (evidencia de T-22)

Se hace de punta a punta, en orden. Antes: base y API encendidas, admin creado. Cada paso tiene su resultado esperado.

### Como admin

| # | Paso | Resultado esperado |
|---|---|---|
| 1 | Entrar con `admin` y una clave **equivocada** | Mensaje «Usuario o clave incorrectos.» (`INVALID_CREDENTIALS`); no dice cuál falló |
| 2 | Entrar con la clave correcta | Menú con 7 opciones, incluidas **Reporte** y **Mentores**; arriba dice `admin · admin` |
| 3 | Mentores → crear `laura` con una clave | «Mentor "laura" creado» |
| 4 | Mentores → crear `LAURA` otra vez | Error `USERNAME_TAKEN` |
| 5 | Kits → Nuevo kit: `Dron Tello EDU`, `DJI`, `DRN-0001`, Drones, 1 unidad | «Kit creado.»; aparece con `1 de 1` |
| 6 | Kits → Nuevo kit con referencia `drn-1` | Se escribe `DRN-1` y la API responde `VALIDATION_ERROR` en `reference` |
| 7 | Kits → Nuevo kit: `Arduino Starter Kit`, `Arduino`, `ARD-0001`, Iniciación, 3 unidades | Aparece con `3 de 3` |
| 8 | En el Arduino → **Foto** → elegir un PNG o JPG de menos de 2 MB | «Foto guardada.»; aparece **Ver foto** y la muestra |
| 9 | Subir como foto un archivo que no es imagen (por ejemplo un `.txt` renombrado a `.png`) | `VALIDATION_ERROR` en `file` |
| 10 | Participantes → registrar `Ana Gómez`, documento `1020304050` | «Participante "Ana Gómez" registrado.» |
| 11 | Registrar otra persona con el **mismo documento** | `DOCUMENT_TAKEN` |
| 12 | Salir | Vuelve al login |

### Como mentor

| # | Paso | Resultado esperado |
|---|---|---|
| 13 | Entrar como `laura` | Menú con 5 opciones: **no** aparecen Reporte ni Mentores |
| 14 | Kits | Puede buscar, pero **no** ve Nuevo kit, Editar, Foto ni Dar de baja |
| 15 | Prestar → Ana → elegir el **Dron** y el **Arduino** → Prestar 2 kit(s) | «Préstamo registrado»; cada kit vence 12 días después |
| 16 | Kits | Dron `0 de 1` (en rojo) y Arduino `2 de 3` |
| 17 | Prestar → Ana → elegir otro kit | `PARTICIPANT_LIMIT_EXCEEDED`, con «sin devolver: 2» |
| 18 | Participantes → Ana → Ver kits en su poder | «2 kit(s) sin devolver (máximo 2)» |
| 19 | Préstamos → el de Ana → Devolver el Dron | «Dron Tello EDU devuelto.»; el préstamo sigue **Abierto** |
| 20 | Devolver el Arduino | El préstamo pasa a **Cerrado** |
| 21 | Vencidos | «No hay kits vencidos» (o los del sembrador, con sus días de retraso) |
| 22 | Escribir a mano `#/report` en la dirección | La app no lo muestra; si se llama a la API, responde `FORBIDDEN_ROLE` (403) |
| 23 | Salir | Vuelve al login |

### Otra vez como admin

| # | Paso | Resultado esperado |
|---|---|---|
| 24 | Reporte del mes actual | Dron y Arduino con 1 préstamo cada uno; **sin** nombres de personas |
| 25 | Kits → Editar el Arduino → nombre `Arduino Uno R4` → Guardar | Se renombra en el catálogo |
| 26 | Reporte otra vez | Sigue diciendo **Arduino Starter Kit**: el reporte usa el nombre congelado (artículo VIII) |
| 27 | Kits → Dar de baja el Dron → aceptar | Desaparece del catálogo; en Préstamos el préstamo de Ana lo sigue mostrando |
