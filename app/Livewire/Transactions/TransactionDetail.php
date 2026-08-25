<?php

namespace App\Livewire\Transactions;

use App\Models\Frego\Charge;
use App\Models\Frego\Transaction;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use App\Support\TransactionLock;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detalle de una transacción: encabezado, conceptos y desglose de impuestos.
 *
 * Equivale a `actionView` del `TransactionController` de Yii2, pero el encabezado
 * se lee del mismo motor que alimenta los listados (igual que hacía
 * `findFullModel`), para que los importes cuadren al centavo con lo que enseña la
 * tabla y no se recalculen con otra fórmula.
 */
class TransactionDetail extends Component
{
    public int $transactionId;

    public function mount(int $transaction): void
    {
        $this->transactionId = $transaction;
    }

    /**
     * Fila calculada del encabezado. `showCancelled = 1` para que una transacción
     * cancelada siga siendo consultable, y si no aparece se reintenta en modo
     * cotización: el motor filtra por `booking.mode` y las cotizaciones (modo 9)
     * quedan fuera del modo normal.
     */
    private function headerRow(): object
    {
        foreach ([false, true] as $cotizacion) {
            $filtros = TransactionFilters::make([
                'tran_in' => [$this->transactionId],
                'showCancelled' => 1,
            ]);
            $filtros->showQuatation = $cotizacion;

            $fila = TransactionQuery::make($filtros)->get(1)->first();

            if ($fila !== null) {
                return $fila;
            }
        }

        throw new NotFoundHttpException('No existe la transacción '.$this->transactionId);
    }

    /** @return Collection<int, Charge> */
    private function charges(): Collection
    {
        return Charge::with('chargeType')
            ->where('transaction', $this->transactionId)
            ->orderBy('charge_id')
            ->get();
    }

    public function render()
    {
        $fila = $this->headerRow();

        $transaccion = Transaction::with(['bookingModel', 'currency', 'company', 'client', 'provider'])
            ->findOrFail($this->transactionId);

        return view('livewire.transactions.transaction-detail', [
            'fila' => $fila,
            'transaccion' => $transaccion,
            'cargos' => $this->charges(),
            'candado' => TransactionLock::evaluate(
                $fila,
                (bool) ($transaccion->bookingModel?->locked ?? false),
                auth()->user(),
            ),
        ])->layout('components.app-layout', [
            'title' => trim((string) $transaccion->tran_number) ?: 'Transacción '.$this->transactionId,
        ]);
    }
}
