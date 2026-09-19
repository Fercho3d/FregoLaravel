<?php

namespace App\Livewire\Payments;

use App\Models\Core\Bank;
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
 *
 * Mientras no esté pagada (recién creada o reabierta) se corrige aquí mismo:
 * número, fecha, banco, importe de cada renglón y quitar renglones. Es lo que
 * hacía el modal de Yii2 después de «Open Payment».
 */
class PaymentRequestDetail extends Component
{
    use ChecksPaymentAmounts;

    public int $requestId;

    public string $number = '';

    public string $date = '';

    public string $bankId = '';

    /** @var array<int, string> transc_id => importe aplicado */
    public array $amounts = [];

    /** A dónde regresa el botón «Volver» (el listado con su filtro). */
    public string $volver = '';

    public function mount(int $request): void
    {
        if (PaymentRequest::whereKey($request)->doesntExist()) {
            throw new NotFoundHttpException(__('No se encontró la solicitud de pago.'));
        }

        $this->requestId = $request;
        $this->volver = $this->safeBack((string) request()->query('volver', ''));
        $this->loadEditable();
    }

    /** Llena los campos editables con lo guardado. */
    private function loadEditable(): void
    {
        $solicitud = PaymentRequest::findOrFail($this->requestId);

        $this->number = (string) $solicitud->number;
        $this->date = $solicitud->date ? substr((string) $solicitud->date, 0, 10) : now()->toDateString();
        $this->bankId = (string) $solicitud->bank_id;
        $this->amounts = PaymentByTransaction::where('request_id', $this->requestId)
            ->pluck('amount', 'transc_id')
            ->map(fn ($importe) => (string) round((float) $importe, 2))
            ->all();
    }

    /** Solo se corrige lo que no está pagado. */
    private function assertEditable(): PaymentRequest
    {
        $this->assertAdmin();

        $solicitud = PaymentRequest::findOrFail($this->requestId);

        abort_if((bool) $solicitud->paid, 422, __('Una solicitud pagada no se corrige: primero hay que reabrirla.'));

        return $solicitud;
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
        // Como el «Pay» del modal de Yii2: guarda lo corregido y paga.
        if (! $this->save()) {
            return;
        }

        $solicitud = PaymentRequest::findOrFail($this->requestId);
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

        $this->loadEditable();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' reabierta.');
    }

    /** Guarda el encabezado y los importes corregidos. */
    public function save(): bool
    {
        $solicitud = $this->assertEditable();

        $this->validate([
            'number' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date'],
            'bankId' => ['required', 'exists:bank,bank_id'],
            'amounts.*' => ['required', 'numeric'],
        ], attributes: [
            'number' => __('número'),
            'date' => 'fecha',
            'bankId' => 'banco',
            'amounts.*' => 'importe',
        ]);

        $this->assertAmountsFit($this->transactions(), 'tran_paid_amount');

        if ($this->getErrorBag()->isNotEmpty()) {
            return false;
        }

        foreach ($this->amounts as $transaccion => $importe) {
            PaymentByTransaction::where('request_id', $this->requestId)
                ->where('transc_id', $transaccion)
                ->update(['amount' => round((float) $importe, 2)]);
        }

        $this->saveHeader($solicitud);

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' guardada.');

        return true;
    }

    /** Suelta una transacción de la solicitud; la última no, eso es borrarla. */
    public function removeTransaction(int $transaccion): void
    {
        $solicitud = $this->assertEditable();

        abort_if(count($this->amounts) < 2, 422, __('Es la última transacción: para quitarla, borra la solicitud.'));

        PaymentByTransaction::where('request_id', $this->requestId)->where('transc_id', $transaccion)->delete();
        unset($this->amounts[$transaccion]);

        $this->saveHeader($solicitud);
    }

    /** Número, fecha, banco y el importe, que es la suma de los renglones. */
    private function saveHeader(PaymentRequest $solicitud): void
    {
        $importe = round(collect($this->amounts)->sum(fn ($v) => (float) $v), 2);

        $solicitud->forceFill([
            'number' => $this->number,
            'date' => $this->date,
            'bank_id' => (int) $this->bankId,
            'amount' => $importe,
            'total_to_pay' => $importe,
            'modified_by' => auth()->user()?->usr_id,
        ])->save();
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
            'banks' => Bank::options(),
        ])->layout('components.app-layout', ['title' => __('Solicitud ').$this->folio($this->requestId)]);
    }
}
