<?php

namespace App\Application\Services;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\ParticipantRepository;
use App\Application\Results\ParticipantView;
use App\Domain\Errors\NotFound;
use App\Domain\Participant;

/** HU-05 · Listar y buscar participantes por nombre o documento, paginado (CA-05.2), y ver uno. */
final class SearchParticipantsService
{
    public function __construct(
        private readonly ParticipantRepository $participants,
        private readonly LoanRepository $loans,
    ) {}

    /**
     * Un texto vacío o con solo espacios es lo mismo que no buscar.
     *
     * @return Page<Participant>
     */
    public function search(?string $text, PageRequest $page): Page
    {
        $text = $text === null ? null : trim($text);

        return $this->participants->search($text === '' ? null : $text, $page);
    }

    /** El participante con `active_items`: sus líneas sin devolver, la misma cuenta que usa LendKitsService (RN-2). */
    public function get(string $id): ParticipantView
    {
        $participant = $this->participants->find($id) ?? throw NotFound::resource('el participante');

        return new ParticipantView($participant, count($this->loans->openItemsOf($id)));
    }
}
