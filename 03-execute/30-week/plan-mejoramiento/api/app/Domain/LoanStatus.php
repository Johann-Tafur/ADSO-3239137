<?php

namespace App\Domain;

/** Estado del préstamo. Se calcula, no se guarda (CA-08.1, CA-08.3). */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
