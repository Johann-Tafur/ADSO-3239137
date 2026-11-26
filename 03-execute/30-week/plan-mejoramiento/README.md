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

## Correrlo de punta a punta

Necesitas Docker y Node 20 o superior.

1. **Levantar todo** (base, API y app):

   ```bash
   cd infra
   cp .env.example .env      # completa las claves como explica el archivo
   docker compose up
   ```

   La API corre las migraciones y crea el admin al arrancar. Detalles en [`infra/README.md`](infra/README.md).

2. **Cargar datos de demostración** por la API (40 kits, 10 participantes, 15 préstamos):

   ```bash
   cd tool
   npm run seed              # antes: ADMIN_PASSWORD con la clave del .env
   ```

   Detalles y la medición de RNF-1 en [`tool/README.md`](tool/README.md).

3. **Abrir la app** en `http://localhost:5173` y entrar con `ADMIN_USERNAME` / `ADMIN_PASSWORD`. El recorrido con los
   dos roles está en [`app/README.md`](app/README.md).

4. **Abrir la página pública**: [`page/index.html`](page/index.html) se abre directo en el navegador, sin la API.

5. **Correr las pruebas** de la API (las de integración necesitan la base del paso 1 encendida):

   ```bash
   cd api
   php artisan test
   ```

## Compromiso académico

El compromiso está en el acta [`Llamado-Atencion1-A10.pdf`](Llamado-Atencion1-A10.pdf) (versión con código, sin datos
personales). Reemplaza al plan de mejoramiento anterior; la carpeta conserva el nombre `plan-mejoramiento/`, que es la
indicada para la entrega.
