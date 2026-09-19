<?php

namespace App\Livewire\Payments;

use App\Actions\Payments\CreatePaymentRequest;
use App\Models\Core\Bank;
use App\Models\Core\Transaction;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Alta de una solicitud de pago a partir de las transacciones seleccionadas en
 * el listado.
 *
 * En Yii2 esto era un modal que recibía las casillas marcadas de la rejilla; el
 * flujo es el mismo, pero aquí la pantalla tiene dirección propia y los importes
 * se pueden ajustar renglón por renglón antes de guardar.
 */
class PaymentRequestForm extends Component
{
    use ChecksPaymentAmounts;

    /** @var int[] */
    public array $ids = [];

    public string $number = '';

    public string $date = '';

    public string $bankId = '';

    /** @var array<int, string> transc_id => importe a aplicar */
    public array $amounts = [];

    /**
     * A dónde vuelve «Cancelar»: la pantalla de la que se llegó, con su filtro.
     *
     * Antes siempre caía en el listado de solicitudes, aunque se viniera de
     * Facturas o de Costos, y había que rehacer el filtro a mano.
     */
    public string $back = '';

    public function mount(): void
    {
        $this->ids = collect(explode(',', (string) request()->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($this->ids === []) {
            throw new NotFoundHttpException(__('No se seleccionó ninguna transacción.'));
        }

        $this->back = $this->safeBack((string) request()->query('volver', ''));
        $this->date = now()->toDateString();

        foreach ($this->transactions() as $transaccion) {
            $this->amounts[$transaccion->transc_id] = (string) round((float) $transaccion->left_to_pay, 2);
        }
    }

    /**
     * Las transacciones seleccionadas, con los mismos modos que usaba el
     * original al armar la solicitud: importes en su divisa y con signo.
     *
     * @return Collection<int, object>
     */
    public function transactions(): Collection
    {
        $filtros = TransactionFilters::make(['tran_in' => $this->ids]);
        $filtros->noExchange = true;
        $filtros->invoiceMode = true;
        $filtros->paymentMode = true;

        return TransactionQuery::make($filtros)->get();
    }

    /**
     * Solo se acepta una ruta INTERNA.
     *
     * El destino llega en la dirección, o sea de fuera: sin este filtro
     * bastaría con cambiar `volver` por otro sitio para que el botón
     * «Cancelar» de nuestra pantalla llevara a donde quisiera quien armara el
     * enlace. Se conserva únicamente la ruta y su cadena de consulta.
     */
    private function safeBack(string $destino): string
    {
        if ($destino === '' || ! str_starts_with($destino, '/') || str_starts_with($destino, '//')) {
            return '';
        }

        $partes = parse_url($destino);

        if ($partes === false || isset($partes['host']) || isset($partes['scheme'])) {
            return '';
        }

        return $partes['path'].(isset($partes['query']) ? '?'.$partes['query'] : '');
    }

    /** De dónde se vino, o el listado de solicitudes si no consta. */
    public function backUrl(): string
    {
        return $this->back !== '' ? $this->back : route('payments.requests', absolute: false);
    }

    public function isCollection(): bool
    {
        $primera = $this->transactions()->first();

        return $primera !== null && (int) $primera->tran_type === Transaction::TYPE_INVOICE;
    }

    public function total(): float
    {
        return round(collect($this->amounts)->sum(fn ($v) => (float) $v), 2);
    }

    public function save(CreatePaymentRequest $crear): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

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

        $transacciones = $this->transactions();

        $this->assertAmountsFit($transacciones, 'left_to_pay');

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $importes = collect($this->amounts)
            ->mapWithKeys(fn ($valor, $id) => [(int) $id => round((float) $valor, 2)])
            ->all();

        $solicitud = $crear->handle(
            $transacciones,
            ['number' => $this->number, 'date' => $this->date, 'bank_id' => $this->bankId],
            $importes,
            auth()->user(),
        );

        session()->flash('status', __('Solicitud ').str_pad((string) $solicitud->request_id, 4, '0', STR_PAD_LEFT).' creada.');

        $this->redirectRoute('payments.requests', [
            'num' => $solicitud->number,
            'nueva' => $solicitud->request_id,
        ], navigate: true);
    }

    public function render()
    {
        return view('livewire.payments.payment-request-form', [
            'transacciones' => $this->transactions(),
            'banks' => Bank::options(),
        ])->layout('components.app-layout', [
            'title' => $this->isCollection() ? __(__('Nueva solicitud de cobro')) : __(__('Nueva solicitud de pago')),
        ]);
    }
}
