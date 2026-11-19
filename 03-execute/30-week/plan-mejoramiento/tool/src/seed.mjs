// Sembrador de demostración (T-21). Todo pasa por la API, con las mismas reglas que una persona.
//
//   npm run seed                 → 40 kits, 10 participantes y 15 préstamos (algunos ya devueltos)
//   npm run perf                 → además crea 5000 kits y mide GET /kits con búsqueda (RNF-1: < 300 ms)
//   node src/seed.mjs --kits 800 → cualquier cantidad
//
// Variables: ROBOTICS_API (por defecto http://localhost:8000/api/v1), ADMIN_USERNAME y ADMIN_PASSWORD.

import { performance } from 'node:perf_hooks';
import { ApiError, createClient } from './http.mjs';
import { bulkKits, demoKits, demoParticipants, randomInt } from './data.mjs';

const RNF1_LIMIT_MS = 300;
const DEMO = { kits: 40, participants: 10, loans: 15 };

function argument(name) {
  const index = process.argv.indexOf(`--${name}`);
  return index === -1 ? undefined : process.argv[index + 1];
}

function randomLetters() {
  return Array.from({ length: 3 }, () => String.fromCharCode(65 + randomInt(0, 25))).join('');
}

/** Crea un kit; si la referencia ya está tomada (otra corrida), prueba con el número siguiente. */
async function createKit(client, kit) {
  for (let attempt = 0; attempt < 20; attempt++) {
    try {
      return await client.post('/kits', kit);
    } catch (error) {
      if (!(error instanceof ApiError) || error.code !== 'REFERENCE_TAKEN') throw error;
      const [prefix, number] = kit.reference.split('-');
      kit = { ...kit, reference: `${prefix}-${String((Number(number) + 37) % 10000).padStart(4, '0')}` };
    }
  }
  throw new Error(`No se encontró una referencia libre para ${kit.name}.`);
}

async function seedDemo(client, categoryIdByName) {
  const start = randomInt(0, 9000);
  const kits = [];
  for (const kit of demoKits(DEMO.kits, categoryIdByName, start)) kits.push(await createKit(client, kit));
  console.log(`  ✓ ${kits.length} kits`);

  const participants = [];
  for (const p of demoParticipants(DEMO.participants)) participants.push(await client.post('/participants', p));
  console.log(`  ✓ ${participants.length} participantes`);

  // 15 préstamos respetando las reglas: máximo 2 kits sin devolver por persona (RN-2).
  // Cada participante presta, y en las vueltas siguientes se devuelve lo anterior antes de volver a prestar.
  let loans = 0;
  let returned = 0;
  const lastLoan = new Map();
  for (let i = 0; loans < DEMO.loans; i++) {
    const person = participants[i % participants.length];
    const previous = lastLoan.get(person.id);
    if (previous) {
      for (const item of previous.items) {
        await client.post(`/loans/${previous.id}/items/${item.id}/return`);
        returned++;
      }
    }
    const howMany = randomInt(1, 2);
    const chosen = new Set();
    while (chosen.size < howMany) chosen.add(kits[randomInt(0, kits.length - 1)].id);
    try {
      const loan = await client.post('/loans', { participant_id: person.id, items: [...chosen].map((kit_id) => ({ kit_id })) });
      lastLoan.set(person.id, loan);
      loans++;
    } catch (error) {
      // Si un kit se quedó sin unidades se intenta con otros: es una regla del negocio, no una falla
      if (!(error instanceof ApiError) || error.code !== 'KIT_NOT_AVAILABLE') throw error;
    }
  }
  console.log(`  ✓ ${loans} préstamos (${returned} línea(s) ya devuelta(s))`);
}

async function seedBulk(client, count, categoryIds) {
  const prefix = randomLetters();
  console.log(`  Creando ${count} kits de carga (referencias ${prefix}-xxxx)…`);
  const kits = bulkKits(count, categoryIds, prefix);
  const started = performance.now();
  // Lotes de 10 en paralelo: más rápido sin ahogar a la API
  for (let i = 0; i < kits.length; i += 10) {
    await Promise.all(kits.slice(i, i + 10).map((kit) => createKit(client, kit)));
    if ((i + 10) % 500 === 0) console.log(`    ${i + 10} / ${count}`);
  }
  console.log(`  ✓ ${count} kits en ${((performance.now() - started) / 1000).toFixed(1)} s`);
}

/** RNF-1: GET /kits con búsqueda. Se descarta la primera (calentamiento) y se toma la mediana de 10. */
async function measureSearch(client) {
  const times = [];
  for (let i = 0; i < 11; i++) {
    const started = performance.now();
    const page = await client.get('/kits?q=kit&size=20');
    const elapsed = performance.now() - started;
    if (i > 0) times.push(elapsed);
    if (i === 0) console.log(`  Catálogo con búsqueda «kit»: ${page.total} resultado(s)`);
  }
  times.sort((a, b) => a - b);
  const median = (times[4] + times[5]) / 2;
  console.log(`  GET /kits?q=kit → mediana ${median.toFixed(1)} ms (mín. ${times[0].toFixed(1)}, máx. ${times.at(-1).toFixed(1)})`);
  return median;
}

async function main() {
  const baseUrl = process.env.ROBOTICS_API ?? 'http://localhost:8000/api/v1';
  const username = process.env.ADMIN_USERNAME ?? 'admin';
  const password = process.env.ADMIN_PASSWORD;
  if (!password) {
    console.error('Falta ADMIN_PASSWORD (la clave del admin de api/.env). Ejemplo en PowerShell: $env:ADMIN_PASSWORD="…"');
    process.exit(2);
  }
  const bulk = argument('kits') === undefined ? 0 : Number(argument('kits'));
  if (!Number.isInteger(bulk) || bulk < 0) {
    console.error('--kits debe ser un número entero, por ejemplo --kits 5000');
    process.exit(2);
  }

  const client = createClient(baseUrl);
  await client.login(username, password);
  console.log(`Sesión iniciada como ${username} en ${baseUrl}`);

  const categories = (await client.get('/categories')).items;
  const categoryIdByName = Object.fromEntries(categories.map((c) => [c.name, c.id]));

  console.log('Datos de demostración:');
  await seedDemo(client, categoryIdByName);

  const total = (await client.get('/kits?size=1')).total;
  const report = await client.get(`/reports/most-requested?from=${new Date().toISOString().slice(0, 7)}-01&to=${new Date().toISOString().slice(0, 10)}`);
  console.log(`Comprobación: /kits devuelve ${total} kit(s) y el reporte del mes tiene ${report.rows.length} fila(s).`);

  if (bulk > 0) {
    console.log(`Medición RNF-1 con ${bulk} kits más:`);
    await seedBulk(client, bulk, categories.map((c) => c.id));
    const median = await measureSearch(client);
    if (median > RNF1_LIMIT_MS) {
      console.error(`✗ RNF-1 NO se cumple: ${median.toFixed(1)} ms > ${RNF1_LIMIT_MS} ms`);
      process.exit(1);
    }
    console.log(`✓ RNF-1 se cumple: ${median.toFixed(1)} ms ≤ ${RNF1_LIMIT_MS} ms`);
  }
}

main().catch((error) => {
  console.error(`✗ ${error.message}`);
  process.exit(1);
});
