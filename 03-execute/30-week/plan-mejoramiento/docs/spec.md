# Especificación — Robotics

**Qué se construye y por qué.** Un sistema para que un semillero de robótica educativa lleve su catálogo de kits,
sus participantes y los préstamos de kits, y vea qué kits se solicitan más.

Rige bajo [`constitution.md`](constitution.md). El modelo de datos vive solo en [`data-model.md`](data-model.md)
y el contrato HTTP solo en [`api-contract.md`](api-contract.md).

---

## 1. Actores

| Actor | Qué hace | Cómo se autentica |
|---|---|---|
| **Administrador** (`admin`) | Mantiene el catálogo de kits y los participantes; da de alta mentores; ve el reporte | Usuario y contraseña → token |
| **Mentor** (`mentor`) | Registra préstamos y devoluciones; consulta catálogo, participantes y vencidos | Usuario y contraseña → token |
| **Participante** | Persona que pide kits prestados. **No entra al sistema**: es un dato, no un usuario | — |

El rol `admin` lo crea el despliegue a partir de variables de entorno. **Nadie lo otorga en ejecución.**

## 2. Alcance

**Dentro:** catálogo de kits con foto opcional, cinco categorías fijas, participantes, préstamos con una o dos líneas,
devolución por línea, kits vencidos, reporte de kits más solicitados, inicio de sesión con roles.

**Fuera, y no se reabre:** multas y pagos, reservas, renovaciones, correo o SMS, importación masiva, préstamos entre sedes,
inventario de piezas sueltas, atributos de kit más allá de los de §3.

**Categorías sembradas (fijas):** iniciación, sensores, drones, impresión 3D, competencia.

## 3. Historias de usuario y criterios de aceptación

### HU-01 · Iniciar sesión
Como usuario quiero autenticarme para operar según mi rol.
- **CA-01.1** Con credenciales válidas se recibe un token con vigencia de 8 horas.
- **CA-01.2** Con credenciales inválidas responde 401 con un mensaje que **no** dice si falló el usuario o la clave.
- **CA-01.3** Todo endpoint salvo el inicio de sesión y `/health` exige token. Sin token: 401. Rol insuficiente: 403.

### HU-02 · Dar de alta mentores (admin)
- **CA-02.1** El admin crea un usuario con nombre de usuario, clave y rol `mentor`.
- **CA-02.2** El nombre de usuario se guarda en minúsculas y sin espacios al inicio ni al final; es único. Repetido: 409.
- **CA-02.3** Pedir el rol `admin` por la API responde 422. La clave nunca se devuelve ni se registra en logs.

### HU-03 · Gestionar kits (admin)
- **CA-03.1** Crear kit con nombre, fabricante, referencia, categoría y total de unidades. Las unidades disponibles arrancan iguales al total.
- **CA-03.2** La referencia tiene el formato `AAA-0000` (tres letras mayúsculas, guion, cuatro dígitos) y es única entre kits activos. Repetida: 409.
- **CA-03.3** Total de unidades ≥ 1. Cambiar el total ajusta las disponibles en la misma diferencia y **nunca** deja disponibles < 0 (422).
- **CA-03.4** Subir foto (JPG/PNG, máx. 2 MB) guarda una **clave opaca**; sin foto el campo es `null`, nunca cadena vacía.
- **CA-03.5** Eliminar un kit es **baja lógica**: deja de aparecer en el catálogo pero sigue en los préstamos pasados. Un kit con préstamos abiertos no se da de baja (409).

### HU-04 · Buscar kits
- **CA-04.1** Búsqueda por texto parcial en nombre o fabricante, filtro por categoría, solo kits activos, orden por nombre, paginada (`page`, `size`, máx. 50).
- **CA-04.2** La respuesta incluye el total de resultados.
- **CA-04.3** Consultar un kit por id inexistente o dado de baja: 404.

### HU-05 · Gestionar participantes
- **CA-05.1** El admin y el mentor crean participantes con nombre y documento. El documento es único: repetido 409.
- **CA-05.2** Se listan y se buscan por nombre o documento, paginado.
- **CA-05.3** Los participantes no se eliminan.

### HU-06 · Registrar un préstamo
- **CA-06.1** Un préstamo es de **un** participante, lo registra el usuario autenticado y trae de 1 a 2 líneas.
- **CA-06.2** No se repite el mismo kit en un préstamo (422).
- **CA-06.3** Cada línea descuenta **1** unidad disponible en la misma operación. Sin unidades: 409 y **no se registra nada**.
- **CA-06.4** Un participante no puede tener más de **2 kits sin devolver** en total (422 con el conteo actual).
- **CA-06.5** Un participante con algún kit **vencido** no puede pedir otro (409).
- **CA-06.6** La fecha de vencimiento es `loaned_at + 12 días`. La línea congela nombre del kit, referencia y nombre de categoría **en ese instante**.
- **CA-06.7** El préstamo registrado no se edita ni se borra.

### HU-07 · Registrar una devolución
- **CA-07.1** Se devuelve **una línea** a la vez. Pone `returned_at` y suma 1 a las unidades disponibles.
- **CA-07.2** Devolver una línea ya devuelta: 409. **Nunca** suma dos veces.
- **CA-07.3** Las unidades disponibles jamás superan el total (la base lo garantiza).
- **CA-07.4** Cuando todas las líneas están devueltas, el préstamo figura como **cerrado**.

### HU-08 · Ver un préstamo y listar préstamos
- **CA-08.1** Consultar un préstamo trae sus líneas y su estado calculado (`open`, `overdue`, `closed`).
- **CA-08.2** Listar por rango de fechas, estado y participante, orden por fecha descendente, paginado.
- **CA-08.3** El estado **se calcula**, no se almacena.

### HU-09 · Kits vencidos
- **CA-09.1** Lista las líneas sin devolver con `due_at` anterior a ahora: kit, participante, días de retraso.
- **CA-09.2** Ordenadas por más días de retraso primero. Solo `admin` y `mentor`.

### HU-10 · Reporte de kits más solicitados
- **CA-10.1** Dado un rango de fechas, una fila por **kit y nombre de categoría congelado**, con la cantidad de veces prestado, ordenado de mayor a menor.
- **CA-10.2** Fin anterior al inicio: 422. Rango sin préstamos: lista vacía, no error.
- **CA-10.3** El reporte **no** se desglosa por mentor ni por participante.
- **CA-10.4** Un reporte de un periodo cerrado **no cambia** aunque luego se renombre el kit o su categoría.
- **CA-10.5** Solo `admin`.

## 4. Reglas de negocio

| # | Regla |
|---|---|
| RN-1 | Unidades disponibles entre 0 y total, siempre. |
| RN-2 | Plazo de préstamo: 12 días. Máximo 2 kits sin devolver por participante. |
| RN-3 | Un participante con vencidos no recibe más kits hasta devolverlos. |
| RN-4 | Lo congelado en la línea (nombre, referencia, categoría) no sigue al catálogo. |
| RN-5 | Préstamos y devoluciones no se borran. Los kits solo tienen baja lógica. |
| RN-6 | Las fechas son instantes UTC. Mensajes al usuario en español; código y columnas en inglés. |

## 5. Requisitos no funcionales

| # | Requisito | Cómo se mide |
|---|---|---|
| RNF-1 | La búsqueda de kits responde en menos de 300 ms con 5 000 kits | Prueba de carga con el sembrador de `tool` |
| RNF-2 | Dos mentores entregando la **última unidad** a la vez: uno gana, el otro recibe 409 | Prueba concurrente |
| RNF-3 | La contraseña nunca viaja ni se guarda en claro; nunca aparece en logs | Revisión de logs y de respuestas |
| RNF-4 | Todo error HTTP usa el mismo cuerpo (ver contrato) | Prueba de contrato |
| RNF-5 | El sistema levanta con un solo comando y la base arranca vacía | `README` de `infra` |

## 6. Equivalencias con Manager Library

| Manager Library | Robotics |
|---|---|
| Libro (ISBN, ejemplares) | Kit (referencia, unidades) |
| Socio | Participante |
| Bibliotecario (`librarian`) | Mentor (`mentor`) |
| Préstamo | Préstamo |
| Portada | Foto del kit |
| Plazo 14 días, máx. 3 libros | Plazo 12 días, máx. 2 kits |
| Reporte de libros más prestados | Reporte de kits más solicitados |
