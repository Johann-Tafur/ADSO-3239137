<?php

namespace App\Application\Services;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\KitRepository;
use App\Application\Results\KitView;
use App\Domain\Category;
use App\Domain\Errors\NotFound;
use LogicException;

/** HU-04 · Consultar el catálogo: buscar kits, ver uno y listar las categorías. */
final class SearchKitsService
{
    public function __construct(
        private readonly KitRepository $kits,
        private readonly CategoryRepository $categories,
    ) {}

    /**
     * Texto parcial en nombre o fabricante, filtro por categoría, solo activos, orden por nombre, paginado (CA-04.1, CA-04.2).
     * Un texto vacío o con solo espacios es lo mismo que no buscar.
     *
     * @return Page<KitView>
     */
    public function search(?string $text, ?string $categoryId, PageRequest $page): Page
    {
        $text = $text === null ? null : trim($text);

        return $this->kits->search($text === '' ? null : $text, $categoryId, $page);
    }

    /** Un kit activo; inexistente o dado de baja → NOT_FOUND (CA-04.3). */
    public function get(string $id): KitView
    {
        $kit = $this->kits->findActive($id) ?? throw NotFound::resource('el kit');
        $category = $this->categories->find($kit->categoryId())
            ?? throw new LogicException("El kit {$id} apunta a una categoría que no existe.");

        return new KitView($kit, $category);
    }

    /** @return list<Category> las cinco, ordenadas por nombre */
    public function categories(): array
    {
        return $this->categories->all();
    }
}
