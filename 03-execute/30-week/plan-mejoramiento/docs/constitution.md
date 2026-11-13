# Constitución — Robotics

Principios innegociables del proyecto de préstamo de kits de robótica educativa. Cada artículo dice **cómo se verifica**:
un principio que nadie puede comprobar es solo una intención. Un cambio que viole un artículo se rechaza.

---

## Artículo I — El dominio no depende de nada

`api/app/Domain/` no importa Laravel (`Illuminate\`), ni la capa de aplicación, ni los adaptadores, ni paquetes de `vendor`.
Solo usa PHP puro (`DateTimeImmutable`, excepciones propias).

**Por qué:** así las reglas de préstamo (máximo 2 kits, plazo de 12 días, unidades entre 0 y total) se prueban sin base de datos ni servidor.
**Verificación:** `api/tests/Architecture/LayerDependenciesTest.php` revisa que ningún archivo de `app/Domain` contenga `use Illuminate\`, `use App\Application` ni `use App\Adapters`.

## Artículo II — El núcleo declara sus puertos; el mundo los implementa

`api/app/Application/` solo conoce el dominio. Lo que necesita del exterior (repositorios, reloj, hash de claves, emisor de tokens,
almacenamiento de fotos) lo expresa como una **interfaz que ella misma define** en `app/Application/Ports`.

**Verificación:** el mismo test impide que `app/Application` importe `App\Adapters` o `Illuminate\`. Las pruebas de aplicación usan fakes.

## Artículo III — Un único punto de composición

Solo `api/app/Providers/PortBindingsServiceProvider.php` une cada puerto con su adaptador.

**Verificación:** un test de arquitectura busca `new App\Adapters\Outbound\` y solo lo permite en ese archivo.

## Artículo IV — SOLID no es decorativo

| Principio | Aplicación concreta | Verificación |
|---|---|---|
| **SRP** | Un servicio = un caso de uso. `LendKitsService` presta y no hace nada más | Un servicio con dos razones para cambiar se parte |
| **OCP** | Cambiar dónde se guardan las fotos (disco → nube) no toca dominio ni aplicación | El adaptador nuevo solo cambia una línea en la composición |
| **LSP** | Los fakes de prueba sustituyen al adaptador real sin cambiar expectativas | La misma expectativa pasa contra el fake y el real |
| **ISP** | Puertos pequeños: `Clock`, `PasswordHasher`, `TokenIssuer`, `PhotoStorage` | Ningún implementador deja métodos sin usar |
| **DIP** | El puerto lo define quien lo consume | Los puertos viven en `app/Application/Ports` |

## Artículo V — El esquema es propiedad de las migraciones

`infra` levanta un PostgreSQL **vacío**. Todo el DDL (tablas, índices, restricciones y las cinco categorías sembradas) vive en
`api/database/migrations`. Ver [ADR-001](adr/adr-001-propiedad-del-esquema.md).

**Verificación:** `infra` no contiene `CREATE TABLE`.

## Artículo VI — Las invariantes que la base puede imponer, la base las impone

Toda regla expresable en el motor (`CHECK`, `UNIQUE`, FK) se declara ahí: por ejemplo, `available_units` entre 0 y `total_units`.
Lo que no esté aplicado se marca **pendiente** en [`data-model.md`](data-model.md).

**Verificación:** las consultas de verificación de `data-model.md` coinciden con lo que declara el documento.

## Artículo VII — Lo derivado se calcula, no se almacena

El estado de un préstamo (`open`, `overdue`, `closed`) y los días de retraso se calculan. No hay columna `status` ni `days_late`.

**Verificación:** `data-model.md` no lista esas columnas.

## Artículo VIII — Los valores congelados no siguen al catálogo

La línea de préstamo guarda nombre del kit, referencia y nombre de la categoría **del instante del préstamo**. Ningún reporte los lee del catálogo vivo.

**Verificación:** una prueba renombra un kit después de prestarlo y comprueba que el reporte conserva el nombre anterior.

## Artículo IX — Ningún secreto entra al repositorio ni a un log

Ni contraseñas, ni hashes, ni la clave de firma del token. Todo secreto viene del entorno. Solo se versiona `.env.example`.

**Verificación:** `.env` está en `.gitignore`; ninguna respuesta HTTP incluye `password` ni `password_hash`.

## Artículo X — Gana el motor

Si el documento de datos y la base discrepan, el documento está roto y se corrige el documento.

## Artículo XI — Idioma

Código, clases, variables, columnas y rutas en **inglés**. Documentación y mensajes al usuario en **español**.

**Verificación:** revisión de identificadores; los mensajes de error de la API están en español.

## Artículo XII — Nada se da por hecho sin evidencia

Una tarea de [`tasks.md`](tasks.md) está **hecha** cuando su evidencia existe y se puede ejecutar, no cuando «ya está programada».

## Artículo XIII — Nada fuera del spec

No se construyen funciones que [`spec.md`](spec.md) no pida. Una idea nueva primero entra al spec o no entra.
