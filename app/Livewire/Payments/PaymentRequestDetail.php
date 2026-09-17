<?php

namespace App\Livewire\Payments;

use App\Models\Core\PaymentByTransaction;
use App\Models\Core\PaymentRequest;
use App\Queries\PaymentRequestFilters;
use App\Queries\PaymentRequestQuery;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detalle de una solicitud de pago: su encabezado, las transacciones que agrupa
 * y las acciones (pagar, reabrir, borrar, imprimir).
 *
 * Antes esto se desplegaba dentro de la tabla del listado; ahora es una pantalla
 * propia con dirección, para poder enlazarla y volver a ella. El botón «Volver»
 * regresa al listado tal como venía (mismo filtro).
 */
class PaymentRequestDetail extends Component
{
    public int $requestId;

    /** A dónde regresa el botón «Volver» (el listado con su filtro). */
    public string $volver = '';

    public function mount(int $request): void
    {
        if (PaymentRequest::whereKey($request)->doesntExist()) {
            throw new NotFoundHttpException(__('No se encontró la solicitud de pago.'));
        }

        $this->requestId = $request;
        $this->volver = $this->safeBack((string) request()->query('volver', ''));
    }

    /** Solo se acepta volver a una dirección propia del sistema. */
    private function safeBack(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? $url
            : route('payments.requests', absolute: false);
    }

    public function folio(int|string|null $requestId): string
    {
        return str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
    }

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    // ------------------------------------------------------------ Acciones

    /** Marca la solicitud como pagada (misma regla que el listado). */
    public function markPaid(): void
    {
        $this->assertAdmin();

        $solicitud = PaymentRequest::findOrFail($this->requestId);

        abort_if((bool) $solicitud->paid, 422, __('La solicitud ya está pagada.'));
        abort_if(
            PaymentByTransaction::where('request_id', $this->requestId)->doesntExist(),
            422,
            __('La solicitud no tiene transacciones.'),
        );

        $solicitud->forceFill(['paid' => 1, 'opened' => 0])->save();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' marcada como pagada.');
    }

    /** Vuelve a abrir una solicitud pagada, para corregirla. */
    public function reopen(): void
    {
        $this->assertAdmin();

        PaymentRequest::findOrFail($this->requestId)->forceFill(['paid' => 0, 'opened' => 1])->save();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' reabierta.');
    }

    /** Borra la solicitud y suelta sus transacciones; regresa al listado. */
    public function delete()
    {
        $this->assertAdmin();

        $solicitud = PaymentRequest::findOrFail($this->requestId);

        abort_if((bool) $solicitud->paid, 422, __('Una solicitud pagada no se borra: primero hay que reabrirla.'));

        PaymentByTransaction::where('request_id', $this->requestId)->delete();
        $solicitud->delete();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' borrada.');

        return $this->redirect($this->volver, navigate: true);
    }

    // ------------------------------------------------------------ Consulta

    /** El encabezado de la solicitud, con los mismos campos que el listado. */
    private function header(): ?object
    {
        return PaymentRequestQuery::make(PaymentRequestFilters::make(['request_id' => $this->requestId]))
            ->get()
            ->first();
    }

    /** @return Collection<int, object> */
    private function transactions(): Collection
    {
        $filtros = TransactionFilters::make(['request_id' => $this->requestId]);
        $filtros->paymentMode = true;
        $filtros->noExchange = true;

        return TransactionQuery::make($filtros)->get();
    }

    public function render()
    {
        $solicitud = $this->header();

        abort_if($solicitud === null, 404);

        return view('livewire.payments.payment-request-detail', [
            'solicitud' => $solicitud,
            'transacciones' => $this->transactions(),
        ])->layout('components.app-layout', ['title' => __('Solicitud ').$this->folio($this->requestId)]);
    }
}
