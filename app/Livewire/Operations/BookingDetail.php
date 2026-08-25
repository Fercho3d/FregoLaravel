<?php

namespace App\Livewire\Operations;

use App\Queries\BookingFilters;
use App\Queries\BookingQuery;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detalle de un booking: la ruta, los contenedores, su facturación y el avance
 * de la lista de verificación.
 *
 * Equivale a `actionView` del `BookingController` de Yii2, que repartía lo mismo
 * en varias pestañas.
 */
class BookingDetail extends Component
{
    public int $bookingId;

    public function mount(int $booking): void
    {
        $this->bookingId = $booking;
    }

    /** El encabezado sale del mismo motor que el listado, para que el avance cuadre. */
    private function header(): object
    {
        foreach ([10, 9] as $modo) {
            $filtros = BookingFilters::make([]);
            $filtros->mode = $modo;

            $fila = BookingQuery::make($filtros)->query()
                ->where('b.booking_id', $this->bookingId)
                ->first();

            if ($fila !== null) {
                return $fila;
            }
        }

        throw new NotFoundHttpException('No existe el booking '.$this->bookingId);
    }

    /** @return Collection<int, object> */
    private function containers(): Collection
    {
        return collect(
            DB::table('containers as c')
                ->leftJoin('container_types as ct', 'ct.contType_id', '=', 'c.container_type')
                ->where('c.booking', $this->bookingId)
                ->orderBy('c.container_ID')
                ->get([
                    'c.container_ID', 'c.number', 'c.seal', 'c.quantity', 'c.comodity',
                    'c.pick_up_date', 'ct.container_name',
                ])
        );
    }

    /** Facturas y costos del booking, con la misma aritmética que el módulo. */
    private function transactions(): Collection
    {
        $filtros = TransactionFilters::make(['booking' => $this->bookingId, 'showCancelled' => 1]);

        return TransactionQuery::make($filtros)->get();
    }

    /**
     * Las casillas de la lista de verificación con la fecha en que se marcaron.
     *
     * @return array<string, string|null>
     */
    private function checklist(): array
    {
        $fila = DB::table('check_list')->where('booking', $this->bookingId)->first();

        if ($fila === null) {
            return [];
        }

        $etiquetas = [
            'booking_number' => 'Número de booking', 'pickup_date' => 'Fecha de recolección',
            'modality' => 'Modalidad', 'doc_cut_of' => 'Corte documental', 'SI_date' => 'Instrucciones de embarque',
            'cleared' => 'Despacho aduanal', 'departure' => 'Zarpe', 'bl_payment' => 'Pago del BL',
            'swb' => 'SWB', 'vessel' => 'Buque', 'number' => 'Número', 'client' => 'Cliente',
            'loading_port' => 'Puerto de carga', 'loading_EDT' => 'Fecha de carga',
            'dicharge_port' => 'Puerto de descarga', 'container_type' => 'Tipo de contenedor',
            'commodity' => 'Mercancía', 'set_point' => 'Temperatura', 'dicharge_ETA' => 'Arribo estimado',
            'vacuum_maneuver' => 'Maniobra de vacío', 'draf_client' => 'Draft del cliente',
            'gated_IN' => 'Gate in', 'gated_out' => 'Gate out', 'delivered' => 'Entregado',
            'insurance' => 'Seguro', 'corrected_draft' => 'Draft corregido', 'vgm' => 'VGM',
        ];

        return collect($etiquetas)
            ->mapWithKeys(fn (string $etiqueta, string $campo) => [
                $etiqueta => $fila->{$campo.'_chk_date'} ?? null,
            ])
            ->all();
    }

    public function render()
    {
        $fila = $this->header();

        return view('livewire.operations.booking-detail', [
            'booking' => $fila,
            'contenedores' => $this->containers(),
            'transacciones' => $this->transactions(),
            'checklist' => $this->checklist(),
        ])->layout('components.app-layout', [
            'title' => trim((string) $fila->booking_number) ?: 'Booking '.$this->bookingId,
        ]);
    }
}
