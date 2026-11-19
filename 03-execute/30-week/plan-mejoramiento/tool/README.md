# Tool — Sembrador de Robotics

Llena el sistema con datos de demostración **por la API** (nunca toca la base directamente): los datos pasan por las mismas
reglas que los de una persona. También mide la búsqueda de kits con muchos datos (RNF-1). Es la evidencia de **T-21**.

No tiene dependencias: usa el `fetch` que trae Node 20 o superior.

## Antes de correrlo

La API debe estar encendida (`php artisan serve` en `api/`, o `docker compose up` en `infra/`) y el admin creado.
El sembrador entra como admin con la clave del entorno:

```powershell
$env:ADMIN_PASSWORD = "la clave de ADMIN_PASSWORD de api/.env"
# Opcional si la API no está en http://localhost:8000/api/v1:
$env:ROBOTICS_API = "http://localhost:8000/api/v1"
```

## Datos de demostración

```bash
npm run seed          # o: node src/seed.mjs
```

Crea **40 kits** de las cinco categorías, **10 participantes** y **15 préstamos**. Respeta las reglas: nadie queda con más
de 2 kits sin devolver, así que antes de prestarle otra vez a alguien le devuelve lo anterior.

Resultado esperado:

```text
Sesión iniciada como admin en http://localhost:8000/api/v1
Datos de demostración:
  ✓ 40 kits
  ✓ 10 participantes
  ✓ 15 préstamos (6 línea(s) ya devuelta(s))
Comprobación: /kits devuelve 40 kit(s) y el reporte del mes tiene 19 fila(s).
```

Se puede correr varias veces: si una referencia ya existe, usa el número siguiente.

## Medir la búsqueda (RNF-1)

```bash
npm run perf          # o: node src/seed.mjs --kits 5000
```

Además de la demostración crea **5000 kits** y mide `GET /kits?q=kit`: descarta la primera petición (calentamiento) y toma
la **mediana de 10**. Si pasa de **300 ms** imprime `✗ RNF-1 NO se cumple` y **termina con error** (código 1).

> Crear 5000 kits deja esos kits en la base. Hazlo en una base de pruebas o acepta que el catálogo quede grande.

## Archivos

| Archivo | Qué hace |
|---|---|
| `src/http.mjs` | Cliente de la API: login y peticiones con el token |
| `src/data.mjs` | Nombres de kits y participantes de demostración |
| `src/seed.mjs` | El sembrador y la medición |
