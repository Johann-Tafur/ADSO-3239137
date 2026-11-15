<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-02: migración inicial del esquema robotics (docs/data-model.md §3 a §5 y §9, ADR-001).
 *
 * Se escribe en SQL para que cada tipo, restricción e índice sea exactamente el del documento:
 * ninguna columna lleva DEFAULT, no hay created_at/updated_at y los UUID los genera la aplicación.
 * Las restricciones ck_kit_total_positive, ck_user_role y ck_kit_reference_format NO van aquí: son de T-18.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA robotics');

        DB::statement(<<<'SQL'
            CREATE TABLE robotics.category (
                id   uuid         NOT NULL,
                name varchar(120) NOT NULL,
                CONSTRAINT pk_category PRIMARY KEY (id)
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX uq_category_name ON robotics.category (name)');

        DB::statement(<<<'SQL'
            CREATE TABLE robotics.kit (
                id              uuid         NOT NULL,
                name            varchar(200) NOT NULL,
                manufacturer    varchar(150) NOT NULL,
                reference       char(8)      NOT NULL,
                category_id     uuid         NOT NULL,
                total_units     integer      NOT NULL,
                available_units integer      NOT NULL,
                photo_key       varchar(512),
                version         integer      NOT NULL,
                deleted_at      timestamptz,
                CONSTRAINT pk_kit PRIMARY KEY (id),
                CONSTRAINT fk_kit_category FOREIGN KEY (category_id)
                    REFERENCES robotics.category (id) ON DELETE RESTRICT,
                CONSTRAINT ck_kit_available_range
                    CHECK (available_units >= 0 AND available_units <= total_units)
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX uq_kit_reference_active ON robotics.kit (reference) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX ix_kit_category_name_active ON robotics.kit (category_id, name) WHERE deleted_at IS NULL');

        DB::statement(<<<'SQL'
            CREATE TABLE robotics.participant (
                id              uuid         NOT NULL,
                full_name       varchar(200) NOT NULL,
                document_number varchar(30)  NOT NULL,
                CONSTRAINT pk_participant PRIMARY KEY (id)
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX uq_participant_document_number ON robotics.participant (document_number)');

        DB::statement(<<<'SQL'
            CREATE TABLE robotics."user" (
                id            uuid         NOT NULL,
                username      varchar(120) NOT NULL,
                password_hash varchar(512) NOT NULL,
                role          varchar(40)  NOT NULL,
                CONSTRAINT pk_user PRIMARY KEY (id)
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX uq_user_username ON robotics."user" (username)');

        DB::statement(<<<'SQL'
            CREATE TABLE robotics.loan (
                id                uuid        NOT NULL,
                participant_id    uuid        NOT NULL,
                loaned_by_user_id uuid        NOT NULL,
                loaned_at         timestamptz NOT NULL,
                CONSTRAINT pk_loan PRIMARY KEY (id),
                CONSTRAINT fk_loan_participant FOREIGN KEY (participant_id)
                    REFERENCES robotics.participant (id) ON DELETE RESTRICT,
                CONSTRAINT fk_loan_user FOREIGN KEY (loaned_by_user_id)
                    REFERENCES robotics."user" (id) ON DELETE RESTRICT
            )
        SQL);
        DB::statement('CREATE INDEX ix_loan_loaned_at ON robotics.loan (loaned_at)');
        DB::statement('CREATE INDEX ix_loan_participant ON robotics.loan (participant_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE robotics.loan_item (
                id            uuid         NOT NULL,
                loan_id       uuid         NOT NULL,
                kit_id        uuid         NOT NULL,
                kit_name      varchar(200) NOT NULL,
                kit_reference char(8)      NOT NULL,
                category_name varchar(120) NOT NULL,
                due_at        timestamptz  NOT NULL,
                returned_at   timestamptz,
                CONSTRAINT pk_loan_item PRIMARY KEY (id),
                CONSTRAINT fk_loan_item_loan FOREIGN KEY (loan_id)
                    REFERENCES robotics.loan (id) ON DELETE CASCADE,
                CONSTRAINT fk_loan_item_kit FOREIGN KEY (kit_id)
                    REFERENCES robotics.kit (id) ON DELETE RESTRICT
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX uq_loan_item_loan_kit ON robotics.loan_item (loan_id, kit_id)');
        DB::statement('CREATE INDEX ix_loan_item_kit ON robotics.loan_item (kit_id)');
        DB::statement('CREATE INDEX ix_loan_item_due_open ON robotics.loan_item (due_at) WHERE returned_at IS NULL');

        // Semilla: las cinco categorías con identificadores fijos (data-model.md §9)
        DB::statement(<<<'SQL'
            INSERT INTO robotics.category (id, name) VALUES
                ('11111111-1111-4111-8111-111111111111', 'Iniciación'),
                ('22222222-2222-4222-8222-222222222222', 'Sensores'),
                ('33333333-3333-4333-8333-333333333333', 'Drones'),
                ('44444444-4444-4444-8444-444444444444', 'Impresión 3D'),
                ('55555555-5555-4555-8555-555555555555', 'Competencia')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA robotics CASCADE');
    }
};
