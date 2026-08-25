<?php

namespace App\Livewire\Transactions;

use App\Actions\Transactions\SaveTransaction;
use App\Models\Frego\Account;
use App\Models\Frego\Booking;
use App\Models\Frego\Client;
use App\Models\Frego\Company;
use App\Models\Frego\Provider;
use App\Models\Frego\Transaction;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use App\Support\TransactionLock;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Alta y edición de una transacción.
 *
 * En Yii2 esto era un modal cargado por AJAX (`actionCreate` / `actionUpdate`);
 * aquí es una pantalla con dirección propia, para poder enlazarla y volver a ella.
 *
 * Cuando la transacción está bloqueada (timbrada, pagada o de un booking
 * cerrado), el formulario deja editar **solo la compañía emisora** — misma
 * excepción que hace el original, porque el emisor sí se corrige después.
 */
class TransactionForm extends Component
{
    public ?int $transactionId = null;

    public int $bookingId;

    public int $tranType = Transaction::TYPE_INVOICE;

    // --- Campos del formulario ---
    public string $tranDate = '';

    public string $accountId = '';

    /*
     * Opcionales, y por eso anulables: los selectores vacíos llegan como cadena
     * vacía, que las reglas `exists` rechazarían. Se pasan a null en un solo
     * lugar antes de validar (`blanksToNull`).
     */
    public ?string $tranNumber = null;

    public ?string $companyId = null;

    public ?string $customerId = null;

    public ?string $vendorId = null;

    public ?string $invoiceType = null;

    public ?string $seal = null;

    public ?string $newSeal = null;

    /** Motivo por el que el formulario está bloqueado, o null si se puede editar. */
    public ?string $lockReason = null;

    public function mount(?int $transaction = null, ?int $booking = null, string $tipo = 'factura'): void
    {
        $transaction === null
            ? $this->mountForCreate($booking, $tipo)
            : $this->mountForUpdate($transaction);
    }

    private function mountForCreate(?int $booking, string $tipo): void
    {
        if ($booking === null || ! Booking::whereKey($booking)->exists()) {
            throw new NotFoundHttpException('Falta el booking al que pertenece la transacción.');
        }

        $this->bookingId = $booking;
        $this->tranType = $tipo === 'costo' ? Transaction::TYPE_BILL : Transaction::TYPE_INVOICE;
        $this->tranDate = now()->toDateString();
        $this->invoiceType = (string) Transaction::INVOICE_TYPE_NORMAL;
    }

    private function mountForUpdate(int $transaction): void
    {
        $modelo = Transaction::with('bookingModel')->findOrFail($transaction);

        $this->transactionId = $modelo->transc_id;
        $this->bookingId = (int) $modelo->booking;
        $this->tranType = (int) $modelo->tran_type;
        $this->tranDate = $modelo->tran_date?->toDateString() ?? '';
        $this->accountId = (string) $modelo->account;
        $this->tranNumber = $modelo->tran_number;
        $this->companyId = $this->asOption($modelo->company_id);
        $this->customerId = $this->asOption($modelo->customer);
        $this->vendorId = $this->asOption($modelo->vendor);
        $this->invoiceType = $this->asOption($modelo->invoice_type);
        $this->seal = $modelo->seal;
        $this->newSeal = $modelo->new_seal;

        $this->lockReason = $this->lockFor($modelo)->reason;
    }

    private function asOption(mixed $valor): ?string
    {
        return $valor === null ? null : (string) $valor;
    }

    private function lockFor(Transaction $modelo): TransactionLock
    {
        $filtros = TransactionFilters::make(['tran_in' => [$modelo->transc_id], 'showCancelled' => 1]);
        $filtros->showQuatation = $modelo->bookingModel?->isQuotation() ?? false;

        $fila = TransactionQuery::make($filtros)->get(1)->first() ?? (object) [];

        return TransactionLock::evaluate($fila, (bool) ($modelo->bookingModel?->locked ?? false), auth()->user());
    }

    public function isInvoice(): bool
    {
        return $this->tranType === Transaction::TYPE_INVOICE;
    }

    public function isLocked(): bool
    {
        return $this->lockReason !== null;
    }

    /**
     * El número solo se captura a mano en los costos y en las facturas históricas;
     * en las demás lo asigna el consecutivo al guardar.
     */
    public function numberIsEditable(): bool
    {
        return ! $this->isInvoice()
            || (int) $this->invoiceType === Transaction::INVOICE_TYPE_HISTORY;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'tranDate' => ['required', 'date'],
            'accountId' => ['required', Rule::exists('account', 'account_id')],
            'companyId' => ['nullable', Rule::exists('company', 'company_id')],
            'tranNumber' => ['nullable', 'string', 'max:128'],
            'seal' => ['nullable', 'string', 'max:128'],
            'newSeal' => ['nullable', 'string', 'max:128'],
            'invoiceType' => [Rule::requiredIf($this->isInvoice()), 'nullable', 'integer'],
            'customerId' => [Rule::requiredIf($this->isInvoice()), 'nullable', Rule::exists('client', 'client_id')],
            'vendorId' => [Rule::requiredIf(! $this->isInvoice()), 'nullable', Rule::exists('provider', 'provider_id')],
        ];
    }

    /** Deja en null los campos opcionales que llegaron vacíos desde un selector. */
    private function blanksToNull(): void
    {
        foreach (['tranNumber', 'companyId', 'customerId', 'vendorId', 'invoiceType', 'seal', 'newSeal'] as $campo) {
            if (trim((string) $this->{$campo}) === '') {
                $this->{$campo} = null;
            }
        }
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'tranDate' => 'fecha',
            'accountId' => 'moneda',
            'companyId' => 'compañía',
            'tranNumber' => 'número',
            'invoiceType' => 'tipo de factura',
            'customerId' => 'cliente',
            'vendorId' => 'proveedor',
        ];
    }

    /**
     * Un proveedor solo puede tener un costo por booking. Es `validateVendor()`
     * del modelo de Yii2: evita facturar dos veces el mismo servicio, y el super
     * administrador queda exento porque a veces hay que corregir a mano.
     */
    private function assertVendorIsFree(): void
    {
        if ($this->isInvoice() || auth()->user()?->isSuperAdmin()) {
            return;
        }

        $repetida = Transaction::where('vendor', $this->vendorId)
            ->where('booking', $this->bookingId)
            ->where('tran_type', '<>', Transaction::TYPE_INVOICE)
            ->when($this->transactionId, fn ($q) => $q->where('transc_id', '<>', $this->transactionId))
            ->first();

        if ($repetida !== null) {
            throw ValidationException::withMessages(['vendorId' => sprintf(
                'Este proveedor ya está en la transacción %s: solo se permite un servicio por proveedor y booking.',
                $repetida->tran_number ?: $repetida->transc_id,
            )]);
        }
    }

    public function save(SaveTransaction $guardar): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $modelo = $this->transactionId === null
            ? new Transaction
            : Transaction::with('bookingModel')->findOrFail($this->transactionId);

        // Bloqueada: solo se acepta el cambio de compañía, lo demás se descarta.
        if ($this->isLocked()) {
            $modelo->company_id = $this->companyId === null ? null : (int) $this->companyId;
            $modelo->save();

            session()->flash('status', 'Se actualizó la compañía emisora.');
            $this->redirectRoute('transactions.show', $modelo->transc_id, navigate: true);

            return;
        }

        $this->blanksToNull();
        $this->validate();
        $this->assertVendorIsFree();

        $guardar->handle($modelo, $this->attributesForSave(), auth()->user());

        session()->flash('status', $this->transactionId === null ? 'Transacción creada.' : 'Transacción actualizada.');
        $this->redirectRoute('transactions.show', $modelo->transc_id, navigate: true);
    }

    /** @return array<string, mixed> */
    private function attributesForSave(): array
    {
        $entero = fn (?string $valor) => $valor === null ? null : (int) $valor;

        return [
            'booking' => $this->bookingId,
            'tran_type' => $this->tranType,
            'tran_date' => $this->tranDate,
            'account' => (int) $this->accountId,
            'company_id' => $entero($this->companyId),
            'customer' => $this->isInvoice() ? $entero($this->customerId) : null,
            'vendor' => $this->isInvoice() ? null : $entero($this->vendorId),
            'invoice_type' => $this->isInvoice() ? $entero($this->invoiceType) : null,
            'tran_number' => $this->numberIsEditable() ? $this->tranNumber : null,
            'seal' => $this->seal,
            'new_seal' => $this->newSeal,
        ];
    }

    public function render()
    {
        return view('livewire.transactions.transaction-form', [
            'booking' => Booking::find($this->bookingId),
            'currencies' => Account::options(),
            'companies' => Company::options(),
            'clients' => $this->isInvoice() ? Client::options() : [],
            'providers' => $this->isInvoice() ? [] : Provider::options(),
        ])->layout('components.app-layout', ['title' => $this->title()]);
    }

    public function title(): string
    {
        $sustantivo = $this->isInvoice() ? 'factura' : 'costo';

        return $this->transactionId === null
            ? 'Nueva '.$sustantivo
            : 'Editar '.$sustantivo;
    }
}
