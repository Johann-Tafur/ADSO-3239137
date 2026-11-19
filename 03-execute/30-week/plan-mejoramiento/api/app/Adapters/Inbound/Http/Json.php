<?php

namespace App\Adapters\Inbound\Http;

use App\Application\Paging\Page;
use App\Application\Results\KitView;
use App\Application\Results\LoanView;
use App\Application\Results\OverdueLine;
use App\Domain\Category;
use App\Domain\LoanItem;
use App\Domain\Participant;
use App\Domain\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Convierte los resultados de los casos de uso en el JSON exacto de api-contract.md.
 * Campos en snake_case; fechas ISO 8601 en UTC con «Z» (§1). Un usuario NUNCA lleva su password_hash (RNF-3).
 */
final class Json
{
    public static function instant(?DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @template T
     *
     * @param Page<T> $page
     * @param callable(T): array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function page(Page $page, callable $item): array
    {
        return ['items' => array_map($item, $page->items), 'page' => $page->page, 'size' => $page->size, 'total' => $page->total];
    }

    /** @return array<string, mixed> */
    public static function category(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name];
    }

    /** @return array<string, mixed> */
    public static function kit(KitView $view): array
    {
        $kit = $view->kit;

        return [
            'id' => $kit->id(),
            'name' => $kit->name(),
            'manufacturer' => $kit->manufacturer(),
            'reference' => $kit->reference()->value,
            'category' => self::category($view->category),
            'total_units' => $kit->totalUnits(),
            'available_units' => $kit->availableUnits(),
            'photo_key' => $kit->photoKey(),
        ];
    }

    /** @return array<string, mixed> */
    public static function participant(Participant $participant): array
    {
        return [
            'id' => $participant->id(),
            'full_name' => $participant->fullName(),
            'document_number' => $participant->documentNumber()->value,
        ];
    }

    /** Solo id, nombre y rol: ni la clave ni el hash (RNF-3). @return array<string, mixed> */
    public static function user(User $user): array
    {
        return ['id' => $user->id(), 'username' => $user->username(), 'role' => $user->role()->value];
    }

    /** @return array<string, mixed> */
    public static function item(LoanItem $item): array
    {
        return [
            'id' => $item->id,
            'kit_id' => $item->kitId,
            'kit_name' => $item->kitName,
            'kit_reference' => $item->kitReference,
            'category_name' => $item->categoryName,
            'due_at' => self::instant($item->dueAt),
            'returned_at' => self::instant($item->returnedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function loan(LoanView $view): array
    {
        $loan = $view->loan;

        return [
            'id' => $loan->id(),
            'participant_id' => $loan->participantId(),
            'loaned_by_user_id' => $loan->loanedByUserId(),
            'loaned_at' => self::instant($loan->loanedAt()),
            'status' => $view->status->value,
            'items' => array_map(self::item(...), $loan->items()),
        ];
    }

    /** @return array<string, mixed> */
    public static function overdue(OverdueLine $line): array
    {
        return [
            'loan_id' => $line->loanId,
            'item_id' => $line->item->id,
            'kit_name' => $line->item->kitName,
            'kit_reference' => $line->item->kitReference,
            'participant_name' => $line->participantName,
            'due_at' => self::instant($line->item->dueAt),
            'days_late' => $line->daysLate,
        ];
    }
}
