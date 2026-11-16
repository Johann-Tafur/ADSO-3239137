<?php

namespace Tests\Support;

use App\Application\Ports\LoanRepository;
use App\Domain\Loan;

/** Fake de LoanRepository: guarda los préstamos en un arreglo. */
final class InMemoryLoanRepository implements LoanRepository, Snapshottable
{
    /** @var array<string, Loan> */
    private array $loans = [];

    private int $sequence = 0;

    public function nextId(): string
    {
        return 'loan-id-'.++$this->sequence;
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

    public function add(Loan $loan): void
    {
        $this->loans[$loan->id()] = $loan;
    }

    public function count(): int
    {
        return count($this->loans);
    }

    public function snapshot(): mixed
    {
        return $this->loans;
    }

    public function restoreSnapshot(mixed $snapshot): void
    {
        $this->loans = $snapshot;
    }
}
