<?php

namespace App\Livewire\Parties;

use App\Livewire\Parties\Concerns\PartyFields;
use App\Support\Export\PartiesExport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Clientes y proveedores.
 *
 * No son catálogos planos —llevan datos fiscales, de contacto y de facturación—
 * pero sí son la misma pantalla con distintos campos, así que se resuelven en un
 * componente con dos modos.
 *
 * Aquí solo va la lista; el alta y la edición abren su propia ficha
 * (`PartyForm`), que regresa a esta lista tal como se dejó.
 */
class PartyManager extends Component
{
    use PartyFields;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(string $mode = 'client'): void
    {
        $this->mode = $mode;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'vendor.pagination.app';
    }

    /** Esta lista con su búsqueda y su página: a dónde regresa la ficha. */
    public function currentUrl(): string
    {
        return route($this->listRoute(), array_filter([
            'q' => $this->search,
            'page' => $this->getPage() > 1 ? $this->getPage() : null,
        ]), absolute: false);
    }

    /** La lista con su búsqueda, tal como se ve. */
    private function query(): Builder
    {
        return DB::table($this->table())
            ->when($this->search !== '', function ($q) {
                $q->where(function ($w) {
                    foreach (['fullName', 'rfc', 'email', 'city'] as $columna) {
                        $w->orWhere($columna, 'like', '%'.$this->search.'%');
                    }
                });
            })
            ->orderBy('fullName');
    }

    /** Descarga en CSV de lo que se está viendo: todo el filtro, no la página. */
    public function export(PartiesExport $exportacion): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $campos = $this->fields();

        return $exportacion->stream(
            $this->query(),
            ['ID' => $this->key()] + collect($campos)->mapWithKeys(fn ($d, $campo) => [$d[0] => $campo])->all(),
            collect($campos)->filter(fn ($d) => $d[1] === 'select')->mapWithKeys(fn ($d, $campo) => [$campo => $this->optionsFor($campo)])->all(),
            ($this->isClient() ? 'clientes' : 'proveedores').'-'.now()->format('Ymd-His').'.csv',
        );
    }

    public function render()
    {
        return view('livewire.parties.party-manager', [
            'filas' => $this->query()->paginate(25, ['*'], 'page', $this->getPage()),
        ])->layout('components.app-layout', ['title' => $this->title()]);
    }
}
