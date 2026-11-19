<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-18: bajar a la base tres reglas que hasta ahora vivían solo en el dominio (data-model.md §4).
 * Si algo se saltara el código (un INSERT a mano por psql, un error futuro), PostgreSQL rechaza el dato.
 * Es una migración nueva: la de T-02 ya corrió y no se edita (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Units: un kit tiene al menos 1 unidad (CA-03.3)
        DB::statement('ALTER TABLE robotics.kit ADD CONSTRAINT ck_kit_total_positive CHECK (total_units >= 1)');

        // KitReference: tres letras mayúsculas, guion, cuatro dígitos (CA-03.2)
        DB::statement("ALTER TABLE robotics.kit ADD CONSTRAINT ck_kit_reference_format CHECK (reference ~ '^[A-Z]{3}-[0-9]{4}$')");

        // Role: solo existen admin y mentor (spec.md §2)
        DB::statement("ALTER TABLE robotics.\"user\" ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'mentor'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE robotics."user" DROP CONSTRAINT ck_user_role');
        DB::statement('ALTER TABLE robotics.kit DROP CONSTRAINT ck_kit_reference_format');
        DB::statement('ALTER TABLE robotics.kit DROP CONSTRAINT ck_kit_total_positive');
    }
};
