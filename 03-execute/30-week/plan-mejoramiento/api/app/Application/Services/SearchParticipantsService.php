<?php

namespace App\Application\Services;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\ParticipantRepository;
use App\Domain\Errors\NotFound;
use App\Domain\Participant;

/**
 * HU-05 · Listar y buscar participantes por nombre o documento, paginado (CA-05.2).
 * El conteo de líneas sin devolver (`active_items`) del detalle se agrega con los préstamos (T-09).
 */
final class SearchParticipantsService
{
    public function __construct(private readonly ParticipantRepository $participants) {}

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

    public function get(string $id): Participant
    {
        return $this->participants->find($id) ?? throw NotFound::resource('el participante');
    }
}
