<?php

namespace App\Livewire\Parties;

use App\Livewire\Parties\Concerns\PartyFields;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    public function render()
    {
        $filas = DB::table($this->table())
            ->when($this->search !== '', function ($q) {
                $q->where(function ($w) {
                    foreach (['fullName', 'rfc', 'email', 'city'] as $columna) {
                        $w->orWhere($columna, 'like', '%'.$this->search.'%');
                    }
                });
            })
            ->orderBy('fullName')
            ->paginate(25, ['*'], 'page', $this->getPage());

        return view('livewire.parties.party-manager', [
            'filas' => $filas,
        ])->layout('components.app-layout', ['title' => $this->title()]);
    }
}
