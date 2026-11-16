<?php

namespace App\Application\Ports;

use DateTimeImmutable;

/**
 * Puerto del reloj (plan D-05). La aplicación pregunta la hora aquí y se la pasa al dominio.
 * En producción lo implementa SystemClock; en las pruebas, FixedClock, para no esperar 12 días.
 */
interface Clock
{
    /** Instante actual en UTC. */
    public function now(): DateTimeImmutable;
}
