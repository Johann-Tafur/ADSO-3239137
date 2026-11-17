<?php

namespace Tests\Support;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\LoanRepository;
use App\Application\Results\LoanFilter;
use App\Application\Results\OverdueLine;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

/**
 * Fake de LoanRepository. Como la base, guarda COPIAS: devolver una línea en un Loan leído
 * no cambia lo guardado hasta llamar markReturned().
 */
final class InMemoryLoanRepository implements LoanRepository, Snapshottable
{
    /** @var array<string, Loan> */
    private array $loans = [];

    private int $sequence = 0;

    /** @param InMemoryParticipantRepository|null $participants para el nombre en el listado de vencidos */
    public function __construct(private readonly ?InMemoryParticipantRepository $participants = null) {}

    public function nextId(): string
    {
        return 'loan-id-'.++$this->sequence;
    }

    public function find(string $id): ?Loan
    {
        return isset($this->loans[$id]) ? self::copy($this->loans[$id]) : null;
    }

    public function openItemsOf(string $participantId): array
    {
        $open = [];
        foreach ($this->loans as $loan) {
            if ($loan->participantId() !== $participantId) {
                continue;
            }
            foreach ($loan->items() as $item) {
                if (! $item->isReturned()) {
                    $open[] = $item;
                }
            }
        }

        return $open;
    }

    public function search(LoanFilter $filter, PageRequest $page, DateTimeImmutable $now): Page
    {
        $found = array_values(array_filter($this->loans, fn (Loan $loan) => ($filter->range === null || $filter->range->contains($loan->loanedAt()))
            && ($filter->status === null || $loan->status($now) === $filter->status)
            && ($filter->participantId === null || $loan->participantId() === $filter->participantId)));
        usort($found, fn (Loan $a, Loan $b) => $b->loanedAt() <=> $a->loanedAt());

        $items = array_map(fn (Loan $loan) => self::copy($loan), array_slice($found, $page->offset(), $page->size));

        return new Page($items, $page->page, $page->size, count($found));
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        $lines = [];
        foreach ($this->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->isOverdue($now)) {
                    $name = $this->participants?->find($loan->participantId())?->fullName() ?? $loan->participantId();
                    $lines[] = new OverdueLine($loan->id(), $item, $name);
                }
            }
        }

        return $lines;
    }

    public function add(Loan $loan): void
    {
        $this->loans[$loan->id()] = self::copy($loan);
    }

    public function markReturned(LoanItem $item): void
    {
        foreach ($this->loans as $id => $loan) {
            $items = $loan->items();
            foreach ($items as $position => $stored) {
                if ($stored->id !== $item->id) {
                    continue;
                }
                if ($stored->isReturned()) {
                    throw ItemAlreadyReturned::item($item->id);
                }
                $items[$position] = $stored->withReturnedAt($item->returnedAt);
                $this->loans[$id] = Loan::restore($loan->id(), $loan->participantId(), $loan->loanedByUserId(), $loan->loanedAt(), $items);

                return;
            }
        }
    }

    public function count(): int
    {
        return count($this->loans);
    }

    /** @return list<Loan> todos los préstamos guardados (los usa InMemoryReportReader) */
    public function all(): array
    {
        return array_values(array_map(fn (Loan $loan) => self::copy($loan), $this->loans));
    }

    /** Los préstamos guardados nunca se modifican (markReturned() guarda uno nuevo), así que basta copiar el arreglo. */
    public function snapshot(): mixed
    {
        return $this->loans;
    }

    public function restoreSnapshot(mixed $snapshot): void
    {
        $this->loans = $snapshot;
    }

    private static function copy(Loan $loan): Loan
    {
        return Loan::restore($loan->id(), $loan->participantId(), $loan->loanedByUserId(), $loan->loanedAt(), $loan->items());
    }
}
