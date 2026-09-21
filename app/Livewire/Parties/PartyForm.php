<?php

namespace App\Livewire\Parties;

use App\Livewire\Parties\Concerns\PartyFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Ficha de un cliente o proveedor: alta y edición en su propia pantalla, con los
 * servicios que tiene pactados y regreso a la lista tal como se dejó.
 *
 * Lo que **no** se administra aquí es la contraseña del portal: eso vive en la
 * pantalla de usuarios, junto al resto de los accesos, para no tener dos lugares
 * donde se cambian credenciales.
 */
class PartyForm extends Component
{
    use PartyFields;

    /** Id del tercero, o null si es alta. */
    public ?int $partyId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /**
     * Documentos que se le piden a este cliente (`fields_by_client`).
     *
     * Sin esto, un cliente nuevo no ofrece ni un solo campo donde subir papeles:
     * la pantalla del booking saca los campos de aquí. En Yii2 era un CRUD suelto
     * («Fields by client») que había que ir a buscar por su cuenta.
     *
     * @var array<int, string>
     */
    public array $documentFields = [];

    /** A dónde regresa: la lista con su búsqueda y su página. */
    public string $volver = '';

    public function mount(string $mode = 'client', ?int $party = null): void
    {
        $this->assertAdmin();

        $this->mode = $mode;
        $this->partyId = $party;

        $url = (string) request()->query('volver', '');
        $this->volver = str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? $url
            : route($this->listRoute(), absolute: false);

        if ($party === null) {
            $this->form = collect($this->fields())->map(fn () => '')->all();

            return;
        }

        $fila = DB::table($this->table())->where($this->key(), $party)->first();

        abort_if($fila === null, 404);

        $this->form = collect($this->fields())
            ->mapWithKeys(fn ($definicion, $campo) => [$campo => (string) ($fila->{$campo} ?? '')])
            ->all();
        $this->documentFields = $this->isClient()
            ? DB::table('fields_by_client')->where('client_id', $party)->pluck('field_id')
                ->map(fn ($valor) => (string) $valor)->all()
            : [];
    }

    /** El catálogo de documentos, para las casillas. @return array<int, string> */
    public function documentCatalog(): array
    {
        return DB::table('file_fields')->orderBy('label')
            ->pluck('label', 'field_id')
            ->map(fn ($etiqueta, $id) => (string) ($etiqueta ?: $id))
            ->all();
    }

    /** Servicios pactados con este tercero, con su precio. */
    public function services(): Collection
    {
        if ($this->partyId === null) {
            return collect();
        }

        return DB::table('service as s')
            ->leftJoin('charge_type as ct', 'ct.charge_type_id', '=', 's.charge_type_id')
            ->leftJoin('account as a', 'a.account_id', '=', 's.account_id')
            ->where($this->isClient() ? 's.client_id' : 's.provider_id', $this->partyId)
            ->orderByDesc('s.active')
            ->orderBy('s.description')
            ->get(['s.service_id', 's.description', 's.price', 's.active', 'a.prefix as currency', 'ct.charge_type_name']);
    }

    /** Servicios y precios filtrado a este tercero, con regreso a esta ficha. */
    public function servicesUrl(): string
    {
        $ficha = route($this->listRoute().'.edit', $this->partyId, absolute: false)
            .'?volver='.urlencode($this->volver);

        return route('parties.services', [
            'tipo' => $this->isClient() ? 1 : 2,
            'tercero' => $this->partyId,
            'volver' => $ficha,
        ], absolute: false);
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

        // `email` es NOT NULL en la base: sin correo va vacío, como lo deja el original.
        $valores = collect($this->fields())
            ->mapWithKeys(fn ($d, $campo) => [$campo => ($this->form[$campo] ?? '') === '' ? ($campo === 'email' ? '' : null) : $this->form[$campo]])
            ->all();

        if ($this->partyId === null) {
            $id = DB::table($this->table())->insertGetId(
                array_merge($valores, ['created_by' => auth()->id(), 'created_at' => now()]),
                $this->key(),
            );
        } else {
            $id = $this->partyId;
            DB::table($this->table())
                ->where($this->key(), $id)
                ->update(array_merge($valores, ['modified_by' => auth()->id(), 'modified_at' => now()]));
        }

        $this->syncDocumentFields((int) $id);

        session()->flash('status', $this->partyId === null ? __('Registro creado.') : __('Registro actualizado.'));
        $this->redirect($this->volver, navigate: true);
    }

    /** Deja `fields_by_client` con exactamente los documentos marcados. */
    private function syncDocumentFields(int $clientId): void
    {
        if (! $this->isClient()) {
            return;
        }

        $elegidos = collect($this->documentFields)->map(fn ($id) => (int) $id)->filter()->unique();
        $actuales = DB::table('fields_by_client')->where('client_id', $clientId)->pluck('field_id')
            ->map(fn ($id) => (int) $id);

        DB::table('fields_by_client')
            ->where('client_id', $clientId)
            ->whereIn('field_id', $actuales->diff($elegidos)->all())
            ->delete();

        foreach ($elegidos->diff($actuales) as $fieldId) {
            DB::table('fields_by_client')->insert(['client_id' => $clientId, 'field_id' => $fieldId]);
        }
    }

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    public function render()
    {
        $titulo = match (true) {
            $this->partyId !== null => $this->form['fullName'] ?: __('Editar registro'),
            $this->isClient() => __('Nuevo cliente'),
            default => __('Nuevo proveedor'),
        };

        return view('livewire.parties.party-form', ['titulo' => $titulo])
            ->layout('components.app-layout', ['title' => $titulo]);
    }
}
