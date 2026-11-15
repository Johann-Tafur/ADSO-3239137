<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T-02: el esquema real coincide con docs/data-model.md (consultas de §10).
 * Necesita la base de infra encendida y `php artisan migrate` ya ejecutado.
 */
final class SchemaTest extends TestCase
{
    public function test_robotics_has_exactly_the_six_tables_of_the_data_model(): void
    {
        $tables = $this->column(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'robotics' ORDER BY 1",
            'table_name',
        );

        $this->assertSame(['category', 'kit', 'loan', 'loan_item', 'participant', 'user'], $tables);
    }

    public function test_the_migrations_control_table_lives_outside_robotics(): void
    {
        $schemas = $this->column(
            "SELECT table_schema FROM information_schema.tables WHERE table_name = 'migrations'",
            'table_schema',
        );

        $this->assertSame(['public'], $schemas);
    }

    public function test_no_column_has_a_default(): void
    {
        $withDefault = $this->column(
            "SELECT table_name || '.' || column_name AS col FROM information_schema.columns
             WHERE table_schema = 'robotics' AND column_default IS NOT NULL",
            'col',
        );

        $this->assertSame([], $withDefault, 'data-model.md §3: ninguna columna lleva DEFAULT');
    }

    public function test_engine_constraints_of_section_four_exist(): void
    {
        $constraints = $this->column(
            "SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
             WHERE n.nspname = 'robotics' ORDER BY 1",
            'conname',
        );

        foreach ([
            'pk_category', 'pk_kit', 'pk_participant', 'pk_user', 'pk_loan', 'pk_loan_item',
            'fk_kit_category', 'fk_loan_participant', 'fk_loan_user', 'fk_loan_item_loan', 'fk_loan_item_kit',
            'ck_kit_available_range',
        ] as $expected) {
            $this->assertContains($expected, $constraints);
        }
    }

    public function test_unique_and_access_indexes_exist(): void
    {
        $indexes = $this->column("SELECT indexname FROM pg_indexes WHERE schemaname = 'robotics'", 'indexname');

        foreach ([
            'uq_category_name', 'uq_participant_document_number', 'uq_user_username',
            'uq_kit_reference_active', 'uq_loan_item_loan_kit',
            'ix_kit_category_name_active', 'ix_loan_loaned_at', 'ix_loan_participant',
            'ix_loan_item_kit', 'ix_loan_item_due_open',
        ] as $expected) {
            $this->assertContains($expected, $indexes);
        }
    }

    public function test_the_five_categories_are_seeded_with_fixed_ids(): void
    {
        $categories = DB::select('SELECT id, name FROM robotics.category ORDER BY id');

        $this->assertSame([
            ['11111111-1111-4111-8111-111111111111', 'Iniciación'],
            ['22222222-2222-4222-8222-222222222222', 'Sensores'],
            ['33333333-3333-4333-8333-333333333333', 'Drones'],
            ['44444444-4444-4444-8444-444444444444', 'Impresión 3D'],
            ['55555555-5555-4555-8555-555555555555', 'Competencia'],
        ], array_map(fn ($row) => [$row->id, $row->name], $categories));
    }

    public function test_pending_t18_checks_are_not_in_the_engine_yet(): void
    {
        $checks = $this->column(
            "SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
             WHERE n.nspname = 'robotics' AND c.contype = 'c'",
            'conname',
        );

        // data-model.md §4 los marca «pendiente (T-18)». Cuando T-18 los baje, esta prueba se cambia junto con el documento.
        $this->assertSame(['ck_kit_available_range'], $checks);
    }

    /** @return list<string> */
    private function column(string $sql, string $name): array
    {
        return array_map(fn ($row) => $row->{$name}, DB::select($sql));
    }
}
