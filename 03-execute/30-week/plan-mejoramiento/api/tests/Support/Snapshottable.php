<?php

namespace Tests\Support;

/** Un fake cuyo contenido se puede fotografiar y restaurar, para que FakeTransactionRunner pueda deshacer. */
interface Snapshottable
{
    public function snapshot(): mixed;

    public function restoreSnapshot(mixed $snapshot): void;
}
