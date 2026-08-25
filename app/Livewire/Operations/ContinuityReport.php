<?php

namespace App\Livewire\Operations;

use App\Queries\TransactionFilters;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reporte de continuidad: en qué punto va cada embarque.
 *
 * La tabla `booking_continuity` guarda una fecha por hito (recolección, corte
 * documental, instrucciones, gate in, despacho, zarpe, pago del BL, SWB,
 * entrega, gate out…). Esta pantalla los pone en una rejilla para verlos de
 * corrido, y deja capturarlos ahí mismo: es lo que operación hace todo el día.
 */
class ContinuityReport extends Component
{
    use WithPagination;

    /** Los hitos, en el orden en que ocurren. */
    public const HITOS = [
        'pickup_date' => 'Recolección',
        'doc_cut_of' => 'Corte documental',
        'SI_date' => 'Instrucciones',
        'draf_client' => 'Draft cliente',
        'corrected_draft' => 'Draft corregido',
        'vgm' => 'VGM',
        'gated_IN' => 'Gate in',
        'cleared' => 'Despacho',
        'departure' => 'Zarpe',
        'bl_payment' => 'Pago del BL',
        'swb' => 'SWB',
        'delivered' => 'Entregado',
        'gated_out' => 'Gate out',
        'insurance' => 'Seguro',
    ];

    #[Url(as: 'bk', except: '')]
    public string $bookingNumber = '';

    #[Url(as: 'cliente', except: '')]
    public string $clientName = '';

    /** Rango sobre la fecha de recolección, "dd/mm/aaaa - dd/mm/aaaa". */
    #[Url(as: 'f', except: '')]
    public string $dates = '';

    /** Renglón y columna en captura: "cont_id|hito". */
    public ?string $editing = null;

    public string $value = '';

    public function paginationView(): string
    {
        return 'vendor.pagination.frego';
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['page', 'editing', 'value'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['bookingNumber', 'clientName', 'dates']);
        $this->resetPage();
    }

    /** Abre la captura de un hito concreto de un booking. */
    public function editMilestone(int $bookingId, string $hito, ?string $actual = null): void
    {
        $this->assertAdmin();
        abort_unless(array_key_exists($hito, self::HITOS), 404);

        $this->editing = $bookingId.'|'.$hito;
        $this->value = $actual ? substr($actual, 0, 10) : now()->toDateString();
        $this->resetErrorBag();
    }

    public function saveMilestone(): void
    {
        $this->assertAdmin();

        [$bookingId, $hito] = explode('|', (string) $this->editing);

        abort_unless(array_key_exists($hito, self::HITOS), 404);

        $this->validate(
            ['value' => ['nullable', 'date']],
            attributes: ['value' => mb_strtolower(self::HITOS[$hito])],
        );

        $fila = DB::table('booking_continuity')->where('booking', (int) $bookingId)->first();

        $valores = [$hito => $this->value ?: null, 'modified_by' => auth()->id(), 'modified_at' => now()];

        // La continuidad se crea al vuelo: hay bookings viejos que nunca la
        // tuvieron y no por eso deben quedarse sin captura.
        $fila === null
            ? DB::table('booking_continuity')->insert($valores + ['booking' => (int) $bookingId])
            : DB::table('booking_continuity')->where('cont_id', $fila->cont_id)->update($valores);

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'value']);
        $this->resetErrorBag();
    }

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    public function render()
    {
        $columnas = array_map(fn (string $hito) => "bc.{$hito}", array_keys(self::HITOS));

        $filas = DB::table('booking as b')
            ->leftJoin('booking_continuity as bc', 'bc.booking', '=', 'b.booking_id')
            ->leftJoin('client as c', 'c.client_id', '=', 'b.client')
            ->leftJoin('vessel as v', 'v.vessel_id', '=', 'b.vessel')
            ->where('b.is_draft', 0)
            ->where('b.mode', 10)
            ->when($this->bookingNumber, fn ($q, $v) => $q->where('b.booking_number', 'like', "%{$v}%"))
            ->when($this->clientName, fn ($q, $v) => $q->where('c.fullName', 'like', "%{$v}%"))
            ->when(
                ($rango = TransactionFilters::parseRange($this->dates ?: null)) !== null,
                fn ($q) => $q->whereBetween(DB::raw('DATE(bc.pickup_date)'), $rango)
            )
            ->orderByDesc('b.booking_id')
            ->paginate(25, array_merge([
                'b.booking_id', 'b.booking_number', 'c.fullName as client_name', 'v.vessel_name',
            ], $columnas), 'page', $this->getPage());

        return view('livewire.operations.continuity-report', [
            'filas' => $filas,
        ])->layout('components.app-layout', ['title' => 'Continuidad']);
    }
}
