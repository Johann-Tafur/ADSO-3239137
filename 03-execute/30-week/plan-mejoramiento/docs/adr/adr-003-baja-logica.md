# ADR-003: Baja lógica de kits; préstamos y participantes nunca se borran

- **Estado:** aceptado
- **Fecha:** 2026-11-15
- **Relacionado con:** [spec CA-03.5, CA-04.3, CA-05.3, CA-06.7 y RN-5](../spec.md), [modelo de datos §3, §5 y §8](../data-model.md),
  [contrato `DELETE /kits/{id}`](../api-contract.md)

## Contexto

El admin necesita «eliminar» kits que ya no se prestan (CA-03.5). Pero cada línea de préstamo apunta a un kit
(`loan_item.kit_id`), y el reporte de kits más solicitados (HU-10) y el historial de préstamos (HU-08) tienen que seguir
funcionando con los kits viejos.

Si el kit se borra con `DELETE`, sus líneas quedan apuntando a algo que no existe, o el borrado arrastra el historial. RN-5 dice
que préstamos y devoluciones no se borran nunca.

## Decisión

1. **Kits: baja lógica.** `DELETE /kits/{id}` no ejecuta un `DELETE` en SQL: llena `kit.deleted_at` con la hora UTC actual.
   `deleted_at` nulo = activo.
2. Un kit dado de baja **desaparece del catálogo**: no sale en `GET /kits` y `GET /kits/{id}` responde 404 (CA-04.3). Sigue en
   la base para los préstamos pasados y el reporte.
3. **No se da de baja un kit con préstamos abiertos:** 409 `KIT_HAS_OPEN_LOANS` (CA-03.5).
4. La referencia es única **solo entre kits activos** (índice único parcial `WHERE deleted_at IS NULL`). Un kit nuevo puede usar
   la referencia de uno dado de baja.
5. **Préstamos, líneas y participantes no tienen ninguna forma de borrarse**: ni `DELETE` en la API, ni `deleted_at` en sus
   tablas (CA-05.3, CA-06.7, RN-5).
6. La baja no tiene vuelta atrás desde la API: el spec no pide restaurar kits (artículo XIII).
7. Barrera en la base: `loan_item.kit_id` es FK con `ON DELETE RESTRICT`, así que un `DELETE` manual de un kit con historial
   falla.

## Alternativas consideradas

| Alternativa | Por qué se descarta |
|---|---|
| Borrado físico (`DELETE FROM kit`) | Rompe el historial: las líneas quedarían huérfanas o habría que borrarlas también, lo que viola RN-5 |
| Columna booleana `is_active` | Funciona igual, pero no dice **cuándo** se dio de baja el kit, y la fecha sirve para auditar |
| Mover los kits borrados a otra tabla (`deleted_kit`) | Sería una séptima tabla en `robotics` y duplicaría la estructura de `kit` |
| Baja lógica también en préstamos y participantes | El spec dice que no se eliminan: agregarla sería una función fuera del spec (informe 6) |

## Consecuencias

**A favor**
- El historial y el reporte nunca pierden datos: la línea congela nombre, referencia y categoría (artículo VIII), y el kit sigue
  existiendo.
- Se puede saber cuándo se dio de baja cada kit.
- La referencia de un kit retirado se puede reutilizar.

**En contra**
- **Toda** consulta del catálogo debe filtrar `deleted_at IS NULL`. Si un repositorio lo olvida, reaparecen kits dados de baja;
  por eso las pruebas de integración (T-12) lo comprueban.
- Los kits dados de baja ocupan espacio para siempre.
