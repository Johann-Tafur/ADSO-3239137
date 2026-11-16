<?php

namespace Tests\Unit\Application;

use App\Application\Paging\PageRequest;
use App\Application\Services\CreateParticipantService;
use App\Application\Services\SearchParticipantsService;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use App\Domain\Participant;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryLoanRepository;
use Tests\Support\InMemoryParticipantRepository;

/** T-08 · HU-05: registrar y buscar participantes (CA-05.1, CA-05.2), contra fakes. */
final class ParticipantServicesTest extends TestCase
{
    private InMemoryParticipantRepository $participants;

    private CreateParticipantService $create;

    private SearchParticipantsService $search;

    protected function setUp(): void
    {
        $this->participants = new InMemoryParticipantRepository;
        $this->create = new CreateParticipantService($this->participants);
        $this->search = new SearchParticipantsService($this->participants, new InMemoryLoanRepository);
    }

    /** @param list<Participant> $items @return list<string> */
    private function names(array $items): array
    {
        return array_map(fn (Participant $p) => $p->fullName(), $items);
    }

    // ---------- Registrar ----------

    public function test_creates_a_participant_with_clean_name_and_document(): void
    {
        $participant = $this->create->create(' Ana Gómez ', ' 1020304050 ');

        $this->assertSame('Ana Gómez', $participant->fullName());
        $this->assertSame('1020304050', $participant->documentNumber()->value);
        $this->assertSame($participant, $this->participants->find($participant->id()));
    }

    public function test_a_repeated_document_is_taken(): void
    {
        $this->create->create('Ana Gómez', '1020304050');

        try {
            $this->create->create('Otra persona', ' 1020304050');
            $this->fail('Debió rechazar el documento repetido');
        } catch (DocumentTaken $error) {
            $this->assertSame('DOCUMENT_TAKEN', $error->errorCode());
            $this->assertSame('document_number', $error->details()[0]['field']);
        }
        $this->assertSame(1, $this->participants->count());
    }

    public function test_an_empty_name_or_document_is_a_validation_error(): void
    {
        foreach ([['', '123'], ['Ana', '  ']] as [$name, $document]) {
            try {
                $this->create->create($name, $document);
                $this->fail('Debió rechazar el dato vacío');
            } catch (ValidationError) {
            }
        }
        $this->assertSame(0, $this->participants->count());
    }

    // ---------- Buscar ----------

    private function seed(): void
    {
        $this->create->create('Carlos Ruiz', '9988776655');
        $this->create->create('Ana Gómez', '1020304050');
        $this->create->create('Beatriz Gómez', '1020999999');
    }

    public function test_without_text_it_lists_everyone_by_name(): void
    {
        $this->seed();

        $page = $this->search->search(null, new PageRequest);

        $this->assertSame(['Ana Gómez', 'Beatriz Gómez', 'Carlos Ruiz'], $this->names($page->items));
        $this->assertSame(3, $page->total);
    }

    public function test_searches_by_part_of_the_name_ignoring_case(): void
    {
        $this->seed();

        $this->assertSame(['Ana Gómez', 'Beatriz Gómez'], $this->names($this->search->search('gómez', new PageRequest)->items));
    }

    public function test_searches_by_part_of_the_document(): void
    {
        $this->seed();

        $this->assertSame(['Ana Gómez', 'Beatriz Gómez'], $this->names($this->search->search('1020', new PageRequest)->items));
    }

    public function test_pages_keep_the_total(): void
    {
        $this->seed();

        $page = $this->search->search('  ', new PageRequest(page: 2, size: 2));

        $this->assertSame(['Carlos Ruiz'], $this->names($page->items));
        $this->assertSame(3, $page->total);
    }

    public function test_an_unknown_participant_is_not_found(): void
    {
        $this->expectException(NotFound::class);
        $this->search->get('no-existe');
    }
}
