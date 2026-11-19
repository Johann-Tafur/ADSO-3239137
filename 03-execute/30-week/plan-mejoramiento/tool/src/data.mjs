// Datos de demostración: nombres de kits reales de robótica educativa y participantes inventados.
// Las referencias siguen el formato AAA-0000 (CA-03.2).

/** Kits base por categoría: [prefijo de referencia, nombre, fabricante]. Se combinan con variantes hasta llegar a 40. */
export const KIT_MODELS = {
  'Iniciación': [
    ['ARD', 'Arduino Starter Kit', 'Arduino'],
    ['MBT', 'micro:bit Go', 'Micro:bit Foundation'],
    ['LEG', 'LEGO Education SPIKE Essential', 'LEGO'],
    ['MKB', 'Makey Makey Classic', 'JoyLabz'],
  ],
  'Sensores': [
    ['SEN', 'Kit de 37 sensores', 'Elegoo'],
    ['ULT', 'Sensor ultrasónico HC-SR04', 'Elegoo'],
    ['GRV', 'Grove Beginner Kit', 'Seeed Studio'],
  ],
  'Drones': [
    ['DRN', 'Dron Tello EDU', 'DJI'],
    ['CRZ', 'Crazyflie 2.1', 'Bitcraze'],
  ],
  'Impresión 3D': [
    ['IMP', 'Impresora Ender 3 V3', 'Creality'],
    ['FIL', 'Kit de filamento PLA', 'Sunlu'],
  ],
  'Competencia': [
    ['CMP', 'Robot seguidor de línea', 'Pololu'],
    ['SUM', 'Robot de minisumo', 'JSumo'],
    ['VEX', 'VEX IQ Competition Kit', 'VEX Robotics'],
  ],
};

const VARIANTS = ['', ' (aula 1)', ' (aula 2)', ' (repuesto)'];

const FIRST_NAMES = ['Ana', 'Carlos', 'Diana', 'Esteban', 'Fernanda', 'Gabriel', 'Helena', 'Iván', 'Juliana', 'Kevin', 'Laura', 'Mateo'];
const LAST_NAMES = ['Gómez', 'Ruiz', 'Martínez', 'Rodríguez', 'López', 'Hernández', 'Díaz', 'Moreno', 'Vargas', 'Castro'];

/** Entero aleatorio entre min y max, ambos incluidos. */
export function randomInt(min, max) {
  return min + Math.floor(Math.random() * (max - min + 1));
}

/**
 * Lista de kits de demostración. `start` desplaza los números de referencia para que una segunda corrida
 * no choque con la primera (la referencia es única entre kits activos).
 */
export function demoKits(count, categoryIdByName, start) {
  const flat = Object.entries(KIT_MODELS).flatMap(([category, models]) => models.map((m) => [category, ...m]));
  const kits = [];
  for (let i = 0; kits.length < count; i++) {
    const [category, prefix, name, manufacturer] = flat[i % flat.length];
    const variant = VARIANTS[Math.floor(i / flat.length) % VARIANTS.length];
    kits.push({
      name: `${name}${variant}`,
      manufacturer,
      reference: `${prefix}-${String((start + i) % 10000).padStart(4, '0')}`,
      category_id: categoryIdByName[category],
      total_units: randomInt(1, 5),
    });
  }
  return kits;
}

/** Kits en cantidad para medir la búsqueda (RNF-1): referencias con un prefijo propio de la corrida. */
export function bulkKits(count, categoryIds, prefix) {
  return Array.from({ length: count }, (_, i) => ({
    name: `Kit de carga ${i + 1}`,
    manufacturer: ['Arduino', 'Elegoo', 'DJI', 'Creality', 'Pololu'][i % 5],
    reference: `${prefix}-${String(i).padStart(4, '0')}`,
    category_id: categoryIds[i % categoryIds.length],
    total_units: 1,
  }));
}

export function demoParticipants(count) {
  return Array.from({ length: count }, (_, i) => ({
    full_name: `${FIRST_NAMES[i % FIRST_NAMES.length]} ${LAST_NAMES[(i * 3) % LAST_NAMES.length]}`,
    // Documento con 10 dígitos al azar: casi imposible que se repita entre corridas
    document_number: String(randomInt(1_000_000_000, 1_999_999_999)),
  }));
}
