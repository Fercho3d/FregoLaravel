{{--
    Aviso al cliente de que su booking quedó en firme, con el PDF adjunto.
    Copia de `mail/createdBookingMail.php` de Yii2: mismos campos, mismo orden y
    las mismas fechas en d/m/Y.
--}}
@php
    $fecha = fn ($valor) => empty($valor) ? '' : \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y');
@endphp
<h1>Booking Confirmation: {{ $booking->booking_number }}</h1>

<p>For more details visit <a href="http://portal.frego.com.mx">http://portal.frego.com.mx</a></p>

@if (! empty($cliente?->notification_notes))
    <p>{!! nl2br(e($cliente->notification_notes)) !!}</p>
@endif

<div class="container">
    <table class="details center" style="width:60%" border="1" cellspacing="0" cellpadding="4">
        @foreach ([
            ['Booking ID', $booking->booking_id],
            ['Booking Number', $booking->booking_number],
            ['Vessel', $buque],
            ['Carrier', $naviera],
            ['HB', $booking->HB],
            ['Customer', $cliente?->fullName],
            ['Customer Reference', $booking->customer_reference],
            ['POL', $puertoCarga],
            ['Loading EDT', $fecha($booking->loading_EDT)],
            ['Dicharge Port', $booking->dicharge_port],
            ['Dicharge ETA', $fecha($booking->dicharge_ETA)],
            ['Set Point', $booking->set_point],
            ['Final Destination', $booking->final_destination],
            ['Created At', $fecha($booking->created_at)],
            ['Pick Up Place', $lugarRecoleccion],
        ] as [$etiqueta, $valor])
            <tr>
                <th style="text-align:left">{{ $etiqueta }}</th>
                <td>{{ $valor }}</td>
            </tr>
        @endforeach
    </table>

    @if ($contenedores->isNotEmpty())
        <h1>Containers</h1>

        <table class="details center cien" style="width:80%" border="1" cellspacing="0" cellpadding="4">
            <tr>
                <th>#</th>
                <th>Commodity</th>
                <th>Container Type</th>
                <th>Seal</th>
                <th>Number</th>
            </tr>
            @foreach ($contenedores as $indice => $contenedor)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>{{ $contenedor->comodity }}</td>
                    <td>{{ $contenedor->quantity }}x{{ $contenedor->container_name }}</td>
                    <td>{{ $contenedor->seal }}</td>
                    <td>{{ $contenedor->number }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>
