# ADR-002: Concurrencia optimista con la columna `kit.version`

- **Estado:** aceptado
- **Fecha:** 2026-11-15
- **Relacionado con:** [spec RNF-2 y CA-06.3](../spec.md), [plan D-04 y H-01](../plan.md), [modelo de datos §3](../data-model.md),
  [arquitectura §4](../architecture.md), [contrato `KIT_NOT_AVAILABLE`](../api-contract.md)

## Contexto

RNF-2 pide que, si dos mentores entregan **la última unidad** de un kit al mismo tiempo, uno gane y el otro reciba **409**.
CA-06.3 agrega que, si no hay unidades, **no se registra nada**.

Sin control, las dos peticiones leen `available_units = 1`, las dos restan 1 y las dos guardan. Resultado: dos préstamos para
una sola unidad, y `available_units` en 0 o en -1 según el orden.

## Decisión

1. La tabla `kit` tiene la columna `version integer not null`. Vale 1 al crear el kit y sube 1 en cada escritura.
2. `LendKitsService` lee el kit (con su `version`), aplica las reglas en el dominio (`Kit::withdraw()`) y guarda con:

   ```sql
   UPDATE robotics.kit
      SET available_units = ?, version = version + 1
    WHERE id = ? AND version = ?   -- la versión que se leyó
   ```

3. Si el `UPDATE` afecta **0 filas**, otra petición cambió el kit primero: `KitRepository` lanza `KitChanged` y la
   transacción (`TransactionRunner`) se deshace completa.
   - Al **prestar**, `LendKitsService` lo traduce a **409 `KIT_NOT_AVAILABLE`** (RNF-2): no se guarda el préstamo ni ninguna de
     sus líneas.
   - En las demás escrituras del kit (editar, dar de baja, foto, **devolver**) la API responde **409 `KIT_CHANGED`**. Decir «no
     quedan unidades» a quien está devolviendo o editando sería falso.
4. **Sin reintento automático.** El mentor que pierde ve el error y decide qué hacer.
5. Última barrera en la base: `ck_kit_available_range` (`0 <= available_units <= total_units`). Si algo se escapara del código,
   PostgreSQL rechaza el valor imposible.

## Alternativas consideradas

| Alternativa | Por qué se descarta |
|---|---|
| Bloqueo pesimista (`SELECT … FOR UPDATE`) | También funciona, pero la segunda petición se queda **esperando** a la primera en vez de fallar de inmediato. RNF-2 pide «uno gana, el otro 409», no una fila de espera, y los bloqueos ensucian el dominio con detalles de la base |
| Aislamiento `SERIALIZABLE` en la transacción | PostgreSQL detecta el choque, pero con un error genérico de serialización que habría que traducir, y afecta a todas las consultas de la transacción, no solo al kit |
| Reintentar automáticamente al perder | Esconde el conflicto: el segundo mentor no sabe que el kit se acabó mientras atendía. Además complica las pruebas |
| Confiar solo en `ck_kit_available_range` | Evita el número negativo, pero no detecta el caso en que dos peticiones dejan el kit en 0 «correctamente» con una sola unidad real |

## Consecuencias

**A favor**
- Nadie espera: el que llega segundo recibe 409 al instante.
- La regla vive en un solo lugar (`KitRepository::save`) y se prueba con dos préstamos sobre la última unidad (T-13 en
  integración, T-16 por HTTP).
- La base tiene una segunda defensa (`ck_kit_available_range`).

**En contra**
- Toda escritura del kit debe pasar la `version` leída y subirla; un `UPDATE` que la olvide rompe la protección.
- Con mucha demanda sobre el mismo kit, varios mentores pueden recibir 409 seguidos y tener que intentar de nuevo.
- La versión protege las unidades del kit, no el límite de 2 kits por participante: eso lo resuelve
  [ADR-004](adr-004-bloqueo-del-participante.md).
