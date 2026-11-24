Este proyecto es la solución del llamado de atención académico No. 1 del aprendiz A10, ficha 3239137, dominio robotics.

# Robotics — Préstamo de kits de robótica educativa

Sistema para que un semillero de robótica lleve su catálogo de kits, sus participantes y sus préstamos, y vea qué kits se solicitan más.

## Estructura

| Carpeta | Contenido |
|---|---|
| [`docs/`](docs/) | Especificación: constitución, spec, plan, arquitectura, modelo de datos, contrato de la API, tareas y ADR |
| [`api/`](api/) | Backend en PHP con Laravel |
| [`app/`](app/) | Frontend en React |
| [`page/`](page/) | Sitio público estático que presenta el producto |
| [`infra/`](infra/) | Contenedores: base de datos, API y app con un solo comando |
| [`tool/`](tool/) | Sembrador de datos de demostración que usa solo la API |

## Cómo empezar

Lee primero [`docs/spec.md`](docs/spec.md) y [`docs/constitution.md`](docs/constitution.md).

## Compromiso académico

El compromiso está en el acta [`Llamado-Atencion1-A10.pdf`](Llamado-Atencion1-A10.pdf) (versión con código, sin datos
personales). Reemplaza al plan de mejoramiento anterior; la carpeta conserva el nombre `plan-mejoramiento/`, que es la
indicada para la entrega.
