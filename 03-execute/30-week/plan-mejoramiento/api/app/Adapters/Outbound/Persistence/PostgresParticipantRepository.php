<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\ParticipantRepository;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Participant;
use App\Domain\ValueObjects\DocumentNumber;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/** ParticipantRepository sobre robotics.participant. No hay UPDATE ni DELETE: los participantes no se editan ni se borran. */
final class PostgresParticipantRepository implements ParticipantRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextId(): string
    {
        return Uuid::new();
    }

    public function find(string $id): ?Participant
    {
        return $this->findWhere('id = ?', $id, '');
    }

    /** SELECT … FOR UPDATE: bloquea la fila hasta el fin de la transacción (ADR-004). */
    public function lockForLoan(string $id): ?Participant
    {
        return $this->findWhere('id = ?', $id, ' FOR UPDATE');
    }

    public function findByDocument(DocumentNumber $documentNumber): ?Participant
    {
        $row = $this->db->selectOne(
            'SELECT id, full_name, document_number FROM participant WHERE document_number = ?',
            [$documentNumber->value],
        );

        return $row === null ? null : self::toParticipant($row);
    }

    public function search(?string $text, PageRequest $page): Page
    {
        $where = 'TRUE';
        $bindings = [];
        if ($text !== null) {
            $where = '(full_name ILIKE ? OR document_number ILIKE ?)';
            $bindings = [Sql::contains($text), Sql::contains($text)];
        }

        $total = (int) $this->db->selectOne("SELECT count(*) AS total FROM participant WHERE {$where}", $bindings)->total;
        $rows = $this->db->select(
            "SELECT id, full_name, document_number FROM participant WHERE {$where}
              ORDER BY full_name ".Sql::HUMAN_ORDER.', id LIMIT ? OFFSET ?',
            [...$bindings, $page->size, $page->offset()],
        );

        return new Page(array_map(self::toParticipant(...), $rows), $page->page, $page->size, $total);
    }

    public function add(Participant $participant): void
    {
        try {
            $this->db->insert(
                'INSERT INTO participant (id, full_name, document_number) VALUES (?, ?, ?)',
                [$participant->id(), $participant->fullName(), $participant->documentNumber()->value],
            );
        } catch (QueryException $error) {
            if (Sql::violatesUnique($error, 'uq_participant_document_number')) {
                throw DocumentTaken::document($participant->documentNumber()->value);
            }
            throw $error;
        }
    }

    private function findWhere(string $condition, string $id, string $suffix): ?Participant
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->db->selectOne("SELECT id, full_name, document_number FROM participant WHERE {$condition}{$suffix}", [$id]);

        return $row === null ? null : self::toParticipant($row);
    }

    private static function toParticipant(object $row): Participant
    {
        return Participant::restore($row->id, $row->full_name, new DocumentNumber($row->document_number));
    }
}
