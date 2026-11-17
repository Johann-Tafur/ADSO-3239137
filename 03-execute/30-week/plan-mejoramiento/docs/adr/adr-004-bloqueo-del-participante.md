# ADR-004: Bloqueo de la fila del participante al prestar

- **Estado:** aceptado
- **Fecha:** 2026-11-16
- **Relacionado con:** [spec CA-06.4, CA-06.5, RN-2 y RN-3](../spec.md), [ADR-002](adr-002-concurrencia-optimista.md),
  [arquitectura §4](../architecture.md), [modelo de datos §4](../data-model.md)

## Contexto

`LendKitsService` cuenta las líneas sin devolver del participante antes de prestar: con vencidos responde 409
`PARTICIPANT_HAS_OVERDUE` (RN-3) y si pasaría de 2 responde 422 `PARTICIPANT_LIMIT_EXCEEDED` (RN-2).

ADR-002 protege la fila del **kit**, no al **participante**. Si dos mentores le prestan a Ana **al mismo tiempo**, cada uno con
kits distintos, las dos transacciones leen «0 sin devolver», las dos pasan la regla y las dos guardan: Ana queda con 4 kits.
Los kits no chocan entre sí porque son filas distintas.

La regla no se puede escribir como `CHECK` en la base: mira varias filas de otras tablas (data-model §4, «solo dominio»).

## Decisión

1. Dentro de la transacción del préstamo, lo **primero** que hace `LendKitsService` es leer al participante con
   `ParticipantRepository::lockForLoan()`, que en PostgreSQL es:

   ```sql
   SELECT id, full_name, document_number FROM robotics.participant WHERE id = ? FOR UPDATE
   ```

2. Una segunda petición para el **mismo** participante espera en esa línea hasta que la primera termine. Cuando sigue, ya ve las
   líneas que la primera guardó y la regla cuenta bien.
3. Préstamos de participantes **distintos** no se esperan entre sí: cada uno bloquea su propia fila.
4. Los kits se guardan **ordenados por id**. Así dos préstamos que llevan los mismos kits en distinto orden (Arduino + Dron y
   Dron + Arduino) piden los bloqueos de fila en el mismo orden y no se bloquean mutuamente (*deadlock*).
5. ADR-002 no cambia: las unidades del kit siguen protegidas por `version`.

## Alternativas consideradas

| Alternativa | Por qué se descarta |
|---|---|
| Dejarlo así y documentar el riesgo | Viola RN-2 justo en el momento de más uso (inicio de una sesión del semillero) |
| Columna `version` en `participant` | Exige una migración y una columna que no guarda ningún dato del negocio, y cada préstamo tendría que escribir la fila del participante solo para subir la versión |
| Aislamiento `SERIALIZABLE` | Afecta a todas las consultas de la transacción y falla con un error genérico de serialización que habría que traducir y reintentar |
| Bloqueo consultivo (`pg_advisory_xact_lock`) | Hace lo mismo con un número calculado del UUID; es menos legible y depende de una función propia de PostgreSQL |

**¿Por qué aquí sí un bloqueo y en ADR-002 no?** En el kit, RNF-2 pide que el segundo **falle de inmediato** con 409. Aquí la
espera es lo correcto: el segundo préstamo no está mal, solo necesita saber qué guardó el primero para decidir. La espera dura
lo que tarda una transacción corta.

## Consecuencias

**A favor**
- RN-2 y RN-3 se cumplen también con préstamos simultáneos.
- No se agrega ninguna columna ni tabla.
- El orden fijo de los kits evita *deadlocks* entre préstamos.

**En contra**
- Dos préstamos para la misma persona en el mismo instante se atienden uno detrás de otro.
- El bloqueo solo funciona dentro de una transacción: si alguien llama `lockForLoan()` fuera de `TransactionRunner`, no protege
  nada. Lo cubre la prueba de integración de T-13.
