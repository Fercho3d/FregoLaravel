<?php

namespace App\Livewire\Parties;

use App\Models\Frego\Account;
use App\Models\Frego\Provider;
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
 * Lo que **no** se administra aquí es la contraseña del portal: eso vive en la
 * pantalla de usuarios, junto al resto de los accesos, para no tener dos lugares
 * donde se cambian credenciales.
 */
class PartyManager extends Component
{
    use WithPagination;

    /** client | provider */
    public string $mode = 'client';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public ?int $editing = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(string $mode = 'client'): void
    {
        $this->mode = $mode;
    }

    public function isClient(): bool
    {
        return $this->mode === 'client';
    }

    public function table(): string
    {
        return $this->isClient() ? 'client' : 'provider';
    }

    public function key(): string
    {
        return $this->isClient() ? 'client_id' : 'provider_id';
    }

    public function title(): string
    {
        return $this->isClient() ? 'Clientes' : 'Proveedores';
    }

    /**
     * Campos capturables: etiqueta, tipo y reglas.
     *
     * @return array<string, array{0: string, 1: string, 2: array<int, mixed>}>
     */
    public function fields(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        $comunes = [
            'fullName' => ['Nombre o razón social', 'text', ['required', 'string', 'max:255']],
            'rfc' => ['RFC', 'text', ['nullable', 'string', 'max:20']],
            'email' => ['Correo', 'text', ['nullable', 'email', 'max:255']],
            'phone' => ['Teléfono', 'text', ['nullable', 'string', 'max:50']],
            'address' => ['Dirección', 'text', $texto],
            'city' => ['Ciudad', 'text', ['nullable', 'string', 'max:100']],
            'state' => ['Estado', 'text', ['nullable', 'string', 'max:100']],
            'postal_code' => ['Código postal', 'text', ['nullable', 'string', 'max:20']],
            'account_id' => ['Divisa habitual', 'select', ['nullable', 'integer']],
        ];

        if (! $this->isClient()) {
            return $comunes + [
                'type_id' => ['Tipo de proveedor', 'select', ['nullable', 'integer']],
            ];
        }

        // Datos que solo tienen sentido en un cliente: son los que viajan al CFDI.
        return $comunes + [
            'regimen_fiscal_id' => ['Régimen fiscal (SAT)', 'text', ['nullable', 'string', 'max:10']],
            'invoice_use' => ['Uso del CFDI', 'text', ['nullable', 'string', 'max:10']],
            'pay_method' => ['Método de pago', 'text', ['nullable', 'string', 'max:10']],
            'pay_form' => ['Forma de pago', 'text', ['nullable', 'string', 'max:10']],
            'email_notification' => ['Correos para facturas', 'text', $texto],
        ];
    }

    /** @return array<int, string> */
    public function optionsFor(string $campo): array
    {
        return match ($campo) {
            'account_id' => Account::options(),
            'type_id' => [
                Provider::TYPE_CARRIER => 'Naviera',
                Provider::TYPE_TRANSPORT => 'Transportista',
                Provider::TYPE_BROKER => 'Agente aduanal',
            ],
            default => [],
        };
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'vendor.pagination.frego';
    }

    // ------------------------------------------------------------ Edición

    public function create(): void
    {
        $this->assertAdmin();

        $this->editing = 0;
        $this->form = collect($this->fields())->map(fn () => '')->all();
        $this->resetErrorBag();
    }

    public function edit(int $id): void
    {
        $this->assertAdmin();

        $fila = DB::table($this->table())->where($this->key(), $id)->first();

        abort_if($fila === null, 404);

        $this->editing = $id;
        $this->form = collect($this->fields())
            ->mapWithKeys(fn ($definicion, $campo) => [$campo => (string) ($fila->{$campo} ?? '')])
            ->all();
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'form']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->assertAdmin();

        $this->validate(
            collect($this->fields())->mapWithKeys(fn ($d, $campo) => ["form.{$campo}" => $d[2]])->all(),
            attributes: collect($this->fields())
                ->mapWithKeys(fn ($d, $campo) => ["form.{$campo}" => mb_strtolower($d[0])])
                ->all(),
        );

        $valores = collect($this->fields())
            ->mapWithKeys(fn ($d, $campo) => [$campo => ($this->form[$campo] ?? '') === '' ? null : $this->form[$campo]])
            ->all();

        if ($this->editing === 0) {
            DB::table($this->table())->insert($valores + ['created_by' => auth()->id(), 'created_at' => now()]);
        } else {
            DB::table($this->table())
                ->where($this->key(), $this->editing)
                ->update($valores + ['modified_by' => auth()->id(), 'modified_at' => now()]);
        }

        session()->flash('status', $this->editing === 0 ? 'Registro creado.' : 'Registro actualizado.');
        $this->cancel();
    }

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
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
