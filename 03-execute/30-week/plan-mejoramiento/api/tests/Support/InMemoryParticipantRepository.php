<?php

namespace Tests\Support;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\ParticipantRepository;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Participant;
use App\Domain\ValueObjects\DocumentNumber;

/** Fake de ParticipantRepository: guarda en un arreglo e imita el índice único del documento. */
final class InMemoryParticipantRepository implements ParticipantRepository
{
    /** @var array<string, Participant> por id */
    private array $participants = [];

    private int $sequence = 0;

    public function nextId(): string
    {
        return 'participant-'.++$this->sequence;
    }

    public function find(string $id): ?Participant
    {
        return $this->participants[$id] ?? null;
    }

    public function findByDocument(DocumentNumber $documentNumber): ?Participant
    {
        foreach ($this->participants as $participant) {
            if ($participant->documentNumber()->equals($documentNumber)) {
                return $participant;
            }
        }

        return null;
    }

    public function search(?string $text, PageRequest $page): Page
    {
        $found = array_values(array_filter(
            $this->participants,
            fn (Participant $p) => $text === null
                || mb_stripos($p->fullName(), $text) !== false
                || mb_stripos($p->documentNumber()->value, $text) !== false,
        ));
        usort($found, fn (Participant $a, Participant $b) => strcmp($a->fullName(), $b->fullName()));

        return new Page(array_slice($found, $page->offset(), $page->size), $page->page, $page->size, count($found));
    }

    public function add(Participant $participant): void
    {
        if ($this->findByDocument($participant->documentNumber()) !== null) {
            throw DocumentTaken::document($participant->documentNumber()->value);
        }
        $this->participants[$participant->id()] = $participant;
    }

    public function count(): int
    {
        return count($this->participants);
    }
}
