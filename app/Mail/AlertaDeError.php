<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al responsable del sistema de que algo falló en producción.
 *
 * Texto plano en HTML mínimo y sin plantilla Blade: si lo que falló es el
 * render de vistas, el aviso tiene que poder salir igual.
 */
class AlertaDeError extends Mailable
{
    /** @param  array<string, mixed>  $detalle */
    public function __construct(
        public string $titulo,
        public array $detalle,
        public int $repeticiones = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.mb_strimwidth($this->titulo, 0, 120, '…'),
        );
    }

    public function content(): Content
    {
        $filas = collect($this->detalle)
            ->map(fn ($valor, $clave) => '<b>'.e($clave).':</b> '.nl2br(e(is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))))
            ->implode('<br>');

        $repetido = $this->repeticiones > 0
            ? '<p>Se repitió <b>'.$this->repeticiones.'</b> veces desde el aviso anterior.</p>'
            : '';

        return new Content(htmlString: '<div style="font-family:monospace;font-size:13px">'
            .'<p><b>'.e($this->titulo).'</b></p>'.$repetido.'<p>'.$filas.'</p></div>');
    }
}
