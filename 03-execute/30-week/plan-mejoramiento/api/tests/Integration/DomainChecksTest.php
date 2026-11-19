<?php

namespace Tests\Integration;

use Illuminate\Database\QueryException;

/**
 * T-18: las reglas del dominio también están en la base. Un INSERT que las viole falla aunque nadie pase por el código,
 * igual que si se escribiera a mano por psql (evidencia de tasks.md).
 */
final class DomainChecksTest extends IntegrationTestCase
{
    private const CATEGORY = '11111111-1111-4111-8111-111111111111';

    private function insertKit(string $reference, int $total): void
    {
        $this->db()->insert(
            'INSERT INTO kit (id, name, manufacturer, reference, category_id, total_units, available_units, photo_key, version, deleted_at)
             VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, ?, NULL, 1, NULL)',
            ['Kit', 'Fabricante', $reference, self::CATEGORY, $total, max($total, 0)],
        );
    }

    private function assertViolates(string $constraint, callable $statement): void
    {
        $this->db()->statement('SAVEPOINT check_test');
        try {
            $statement();
            $this->fail("Debió violar {$constraint}");
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->getCode(), 'check_violation');
            $this->assertStringContainsString($constraint, $error->getMessage());
        } finally {
            $this->db()->statement('ROLLBACK TO SAVEPOINT check_test');
        }
    }

    public function test_a_kit_with_zero_units_is_refused(): void
    {
        $this->assertViolates('ck_kit_total_positive', fn () => $this->insertKit('ARD-0001', 0));
    }

    public function test_a_badly_written_reference_is_refused(): void
    {
        foreach (['ard-0001', 'ARD0001', 'AR-00001', 'ARD-001X'] as $bad) {
            $this->assertViolates('ck_kit_reference_format', fn () => $this->insertKit($bad, 1));
        }
    }

    public function test_a_role_other_than_admin_or_mentor_is_refused(): void
    {
        $this->assertViolates('ck_user_role', fn () => $this->db()->insert(
            'INSERT INTO "user" (id, username, password_hash, role) VALUES (gen_random_uuid(), ?, ?, ?)',
            ['root', 'hash', 'superadmin'],
        ));
    }

    public function test_valid_rows_still_pass(): void
    {
        $this->insertKit('ARD-0001', 1);
        $this->db()->insert(
            'INSERT INTO "user" (id, username, password_hash, role) VALUES (gen_random_uuid(), ?, ?, ?)',
            ['laura', 'hash', 'mentor'],
        );

        $this->assertSame(1, (int) $this->db()->selectOne('SELECT count(*) AS n FROM kit')->n);
    }
}
