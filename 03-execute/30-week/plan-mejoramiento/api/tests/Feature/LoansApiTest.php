<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/** T-16 · HU-05 a HU-10 por HTTP: participantes, prestar, devolver, consultar, vencidos y reporte. */
final class LoansApiTest extends ApiTestCase
{
    private string $admin;

    private string $mentor;

    private string $ana;

    /** @var array<string, string> referencia → id */
    private array $kit = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
        $this->mentor = $this->mentor();
        $this->ana = $this->createParticipant($this->mentor, 'Ana Gómez', '1020304050');
        $this->kit['ARD'] = $this->createKit($this->admin, 'ARD-0001', 3, name: 'Arduino');
        $this->kit['SEN'] = $this->createKit($this->admin, 'SEN-0001', 3, self::SENSORES, 'Sensor');
        $this->kit['DRN'] = $this->createKit($this->admin, 'DRN-0001', 1, name: 'Dron');
    }

    /** @param list<string> $kits */
    private function lend(string $participant, array $kits): \Illuminate\Testing\TestResponse
    {
        return $this->api('POST', '/loans', $this->mentor, [
            'participant_id' => $participant,
            'items' => array_map(fn (string $key) => ['kit_id' => $this->kit[$key]], $kits),
        ]);
    }

    // ---------- Participantes (HU-05) ----------

    public function test_participants_are_searched_and_detail_has_active_items(): void
    {
        $this->createParticipant($this->mentor, 'Carlos Ruiz', '999');
        $this->lend($this->ana, ['ARD'])->assertCreated();

        $this->api('GET', '/participants?q=gómez', $this->mentor)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.full_name', 'Ana Gómez');
        $this->api('GET', "/participants/{$this->ana}", $this->mentor)->assertOk()->assertJsonPath('active_items', 1);
        $this->assertError($this->api('POST', '/participants', $this->mentor, ['full_name' => 'Otra', 'document_number' => '1020304050']), 409, 'DOCUMENT_TAKEN');
    }

    // ---------- Prestar (HU-06) ----------

    public function test_a_loan_is_201_with_frozen_lines_due_in_12_days_by_the_token_user(): void
    {
        $response = $this->lend($this->ana, ['ARD', 'SEN'])->assertCreated();

        $response->assertJsonPath('status', 'open')
            ->assertJsonPath('loaned_at', '2026-11-18T15:00:00Z')
            ->assertJsonPath('items.0.due_at', '2026-11-30T15:00:00Z')
            ->assertJsonPath('items.0.returned_at', null);
        $mentorId = DB::selectOne('SELECT id FROM "user" WHERE username = ?', ['laura'])->id;
        $this->assertSame($mentorId, $response->json('loaned_by_user_id'), 'lo registra el usuario del token');
        $this->api('GET', "/kits/{$this->kit['ARD']}", $this->mentor)->assertJsonPath('available_units', 2);
    }

    public function test_the_rules_of_hu06_answer_with_their_codes(): void
    {
        $carlos = $this->createParticipant($this->mentor, 'Carlos', '222');
        $this->lend($carlos, ['DRN'])->assertCreated(); // se lleva el único dron

        $this->assertError($this->lend($this->ana, ['ARD', 'ARD']), 422, 'DUPLICATE_KIT_IN_LOAN');
        $this->assertError($this->lend($this->ana, []), 422, 'VALIDATION_ERROR');
        $this->assertError($this->lend($this->ana, ['ARD', 'DRN']), 409, 'KIT_NOT_AVAILABLE');
        $this->api('GET', "/kits/{$this->kit['ARD']}", $this->mentor)->assertJsonPath('available_units', 3); // no se registró nada

        $this->lend($this->ana, ['ARD', 'SEN'])->assertCreated();
        $limit = $this->lend($this->ana, ['ARD']);
        $this->assertError($limit, 422, 'PARTICIPANT_LIMIT_EXCEEDED');
        $limit->assertJsonPath('details.0', ['field' => 'items', 'issue' => 'sin devolver: 2']);

        $this->clock->advance('+13 days');
        $this->mentor = $this->mentor(); // el token de hace 13 días ya venció (8 h): se inicia sesión otra vez
        $this->assertError($this->lend($carlos, ['SEN']), 409, 'PARTICIPANT_HAS_OVERDUE');
    }

    public function test_an_unknown_participant_or_kit_is_404(): void
    {
        $this->assertError($this->api('POST', '/loans', $this->mentor, ['participant_id' => '00000000-0000-4000-8000-000000000000', 'items' => [['kit_id' => $this->kit['ARD']]]]), 404, 'NOT_FOUND');
        $this->assertError($this->api('POST', '/loans', $this->mentor, ['participant_id' => $this->ana, 'items' => [['kit_id' => 'no-existe']]]), 404, 'NOT_FOUND');
    }

    // ---------- Devolver (HU-07) ----------

    public function test_returning_a_line_is_200_and_twice_is_409(): void
    {
        $loan = $this->lend($this->ana, ['ARD', 'SEN'])->json();
        $item = $loan['items'][0]['id'];
        $this->clock->advance('+2 days');
        $this->mentor = $this->mentor();

        $this->api('POST', "/loans/{$loan['id']}/items/{$item}/return", $this->mentor)
            ->assertOk()->assertJsonPath('returned_at', '2026-11-20T15:00:00Z');
        $this->assertError($this->api('POST', "/loans/{$loan['id']}/items/{$item}/return", $this->mentor), 409, 'ITEM_ALREADY_RETURNED');

        $this->api('GET', "/loans/{$loan['id']}", $this->mentor)->assertOk()->assertJsonPath('status', 'open');
        $this->api('POST', "/loans/{$loan['id']}/items/{$loan['items'][1]['id']}/return", $this->mentor)->assertOk();
        $this->api('GET', "/loans/{$loan['id']}", $this->mentor)->assertJsonPath('status', 'closed');
    }

    // ---------- Consultar y vencidos (HU-08, HU-09) ----------

    public function test_list_filters_and_overdue_lines(): void
    {
        $this->lend($this->ana, ['ARD'])->assertCreated();
        $this->clock->advance('+14 days'); // 2 días de retraso
        $this->mentor = $this->mentor();

        $this->api('GET', '/loans?status=overdue', $this->mentor)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.status', 'overdue');
        $this->api('GET', '/loans?from=2026-11-19&to=2026-11-30', $this->mentor)->assertJsonPath('total', 0);
        $this->assertError($this->api('GET', '/loans?from=2026-11-30&to=2026-11-01', $this->mentor), 422, 'INVALID_DATE_RANGE');
        $this->assertError($this->api('GET', '/loans?from=2026-11-01', $this->mentor), 422, 'VALIDATION_ERROR');

        $this->api('GET', '/loans/overdue', $this->mentor)->assertOk()
            ->assertJsonPath('items.0.participant_name', 'Ana Gómez')
            ->assertJsonPath('items.0.kit_reference', 'ARD-0001')
            ->assertJsonPath('items.0.days_late', 2);
    }

    // ---------- Reporte (HU-10) ----------

    public function test_the_report_counts_frozen_names_without_people(): void
    {
        $this->lend($this->ana, ['ARD'])->assertCreated();

        $report = $this->api('GET', '/reports/most-requested?from=2026-11-01&to=2026-11-30', $this->admin)->assertOk();

        $report->assertJsonPath('from', '2026-11-01')->assertJsonPath('to', '2026-11-30')
            ->assertJsonPath('rows.0.kit_name', 'Arduino')->assertJsonPath('rows.0.times_requested', 1);
        $this->assertSame(['kit_id', 'kit_name', 'category_name', 'times_requested'], array_keys($report->json('rows.0')));
        $this->assertStringNotContainsString('Ana', $report->getContent());
        $this->assertError($this->api('GET', '/reports/most-requested?from=2026-11-01', $this->admin), 422, 'VALIDATION_ERROR');
    }

    // ---------- Cuerpo de error único (RNF-4) ----------

    public function test_an_unknown_route_also_answers_with_the_single_error_body(): void
    {
        $this->assertError($this->api('GET', '/no-existe', $this->mentor), 404, 'NOT_FOUND');
    }

    public function test_no_response_ever_carries_a_password_or_its_hash(): void
    {
        $bodies = [
            $this->api('POST', '/users', $this->admin, ['username' => 'pedro', 'password' => 'clave-pedro', 'role' => 'mentor'])->getContent(),
            $this->postJson(self::API.'/auth/login', ['username' => 'pedro', 'password' => 'clave-pedro'])->getContent(),
            $this->lend($this->ana, ['ARD'])->getContent(),
            $this->api('GET', '/loans', $this->mentor)->getContent(),
        ];

        foreach ($bodies as $body) {
            $this->assertStringNotContainsString('password', $body);
            $this->assertStringNotContainsString('clave-pedro', $body);
            $this->assertStringNotContainsString('$argon2id$', $body);
        }
    }
}
