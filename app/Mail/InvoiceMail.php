<?php

namespace App\Mail;

use App\Models\Frego\Transaction;
use App\Support\TransactionFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La factura timbrada para el cliente: el PDF y el XML.
 *
 * Porta `mail/invoiceMail.php` de Yii2 y el envío que hacen `actionTimbrar` y
 * `actionReenviar`, que mandan exactamente el mismo correo.
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public string $bookingNumber,
    ) {}

    public function envelope(): Envelope
    {
        // El asunto lleva un espacio al final, como en el original.
        return new Envelope(
            subject: 'Invoice Booking ['.$this->bookingNumber.'] ',
            bcc: config('frego.copia_facturas'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice', with: ['bookingNumber' => $this->bookingNumber]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $archivos = app(TransactionFiles::class);

        return collect([TransactionFiles::PDF, TransactionFiles::XML])
            ->map(fn (string $tipo) => $archivos->path($this->transaction, $tipo))
            ->filter()
            ->map(fn (string $ruta) => Attachment::fromPath($ruta))
            ->values()
            ->all();
    }
}
