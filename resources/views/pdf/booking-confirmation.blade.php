{{--
    Confirmación de booking, copia de `Booking::generateBokingConfirmation()` de
    Yii2. La maqueta es la que el cliente recibe desde hace años: mismas tablas,
    mismas clases, mismos textos en inglés y las mismas fechas con hora.

    Los datos de la empresa van fijos, como en el original. Ojo: ahí el RFC está
    escrito a mano (FTM1507038V6) aunque el sistema ya factura con varias
    compañías; se conserva para no cambiar el documento sin que lo pidan.
--}}
@php
    $fecha = fn ($valor) => empty($valor) ? 'no set' : \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y h:i:s A');
    $texto = fn ($valor) => e((string) ($valor ?? ''));
@endphp
<div class="row">
    <div class="column">
        <div class="address">
            Freight Global Operator</br>
            Av Mariano Otero 2347-112 Col. Verde Valle<br/>
            RFC:FTM1507038V6</br>
            Tel: +5233130061992</br>
        </div>
    </div>
    <div class="column">
        <h1 class="confirm-title" style="font-family: sans-serif">{{ $titulo }}</h1>
        <table>
            <tr>
                <td class="backcolor">Reference</td>
                <td>{{ $texto($booking->booking_number) }}<br /></td>
            </tr>
            <tr>
                <td class="backcolor">Creation Date</td>
                <td>{{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d/m/Y h:i:s A') }}<br/></td>
            </tr>
        </table>
    </div>
</div>

<div class="row">
    <div class="column">
        <table>
            <tr>
                <td class="backcolor">Client Information</td>
            </tr>
            <tr>
                <td style="text-align: left; vertical-align: top; height:125px;">
                    {{ $texto($cliente?->fullName) }}<br /><br />
                    {{ $texto($cliente?->address) }}<br />
                    {{ $texto($cliente?->address2) }}<br />
                    {{ $texto($cliente?->city) }}, {{ $texto($cliente?->state) }} {{ $texto($cliente?->postal_code) }}.<br />
                    {{ $texto($cliente?->country) }}<br />
                </td>
            </tr>
        </table>
    </div>
    <div class="column">
        <table>
            <tr>
                <td class="backcolor" colspan="2">Equipment Delivery Address</td>
            </tr>
            <tr>
                <td colspan="2">
                    {{ $texto($recoleccion?->name) }}<br /><br />
                    {{ $texto($recoleccion?->address1) }}<br />
                    {{ $texto($recoleccion?->address2) }}<br />
                    {{ $texto($recoleccion?->city) }}, {{ $texto($recoleccion?->state) }} {{ $texto($recoleccion?->postal_code) }}.<br />
                    {{ $texto($recoleccion?->country) }}<br />
                </td>
            </tr>
            <tr>
                <td class="backcolor">Spotting Date</td>
                <td>{{ $fecha($continuidad?->pickup_date) }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="row">
    <div class="column">
        <table id="table-1">
            <tr>
                <td class="backcolor">Carrier</td>
                <td>{{ $texto($naviera?->fullName) }}</td>
            </tr>
            <tr>
                <td class="backcolor">Pick Up Place</td>
                <td>{{ $texto($recoleccion?->name) }}</td>
            </tr>
            <tr>
                <td class="backcolor">Origin Port</td>
                <td>{{ $texto($puertoCarga) }}</td>
            </tr>
            <tr>
                <td class="backcolor">Destination Port</td>
                <td>{{ $texto($puertoDescarga) }}</td>
            </tr>
            <tr>
                <td class="backcolor">Final Destination</td>
                <td>{{ $texto($destinoFinal) }}</td>
            </tr>
        </table>
        <table id="table-2">
            <tr>
                <td class="backcolor" style="width:43.5%">Cut off Date SI</td>
                <td>{{ $fecha($continuidad?->SI_date) }}</td>
            </tr>
            <tr>
                <td class="backcolor" style="width:43.5%">Port Closing date</td>
                <td>{{ $fecha($continuidad?->doc_cut_of) }}</td>
            </tr>
            <tr>
                <td class="backcolor">Departure Date</td>
                <td>{{ $fecha($booking->loading_EDT) }}</td>
            </tr>
            <tr>
                <td class="backcolor">Arrival Date</td>
                <td>{{ $fecha($booking->dicharge_ETA) }}</td>
            </tr>
        </table>
    </div>
    <div class="column">
        <table id="zero-table">
            <tr>
                <td class="backcolor">Remarks</td>
            </tr>
            <tr>
                <td style="text-align: left; vertical-align: top; height:182px;">{!! nl2br(e((string) $booking->remarks)) !!}</td>
            </tr>
        </table>
    </div>
</div>

<div class="row">
    <div class="full-col">
        <table class="lastTable">
            <tr>
                <td class="backcolor">Number</td>
                <td class="backcolor">Seal</td>
                <td class="backcolor">Container Type</td>
                <td class="backcolor">Commodity</td>
                <td class="backcolor">Quantity</td>
            </tr>
            @foreach ($contenedores as $contenedor)
                <tr>
                    <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($contenedor->number) }}</td>
                    <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($contenedor->seal) }}</td>
                    <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($contenedor->container_name) }}</td>
                    <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($contenedor->comodity) }}</td>
                    <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($contenedor->quantity) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="backcolor"></td>
                <td class="backcolor"></td>
                <td class="backcolor"></td>
                <td class="backcolor">Totals</td>
                <td class="backcolor">Pieces</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>{{ $piezas }}</td>
            </tr>
        </table>
    </div>
</div>

@if ($esCotizacion)
    {{-- La cotización lleva además el desglose de lo que se va a facturar. --}}
    <div class="row" style="margin-top: 20px">
        <div class="full-col">
            <table class="lastTable">
                <tr>
                    <th class="backcolor">Number</th>
                    <th class="backcolor">Amount</th>
                    <th class="backcolor">Currency</th>
                    <th class="backcolor">TC</th>
                    <th class="backcolor">Subtotal %0</th>
                    <th class="backcolor">Subtotal %16</th>
                    <th class="backcolor">VAT 16%</th>
                    <th class="backcolor">Ret VAT</th>
                    <th class="backcolor">Non Deduc</th>
                    <th class="backcolor">Amount</th>
                </tr>
                @foreach ($facturas as $factura)
                    <tr>
                        <td>{{ $texto($booking->booking_number) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->amount_original, 2) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($factura->currency) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ $texto($factura->exchange_value) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->sub_0_mxn, 2) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->sub_16_mxn, 2) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->tax_16_mxn, 2) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->tax_ret_mxn, 2) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->non_dec, 2) }}</td>
                        <td style="text-align: left;  vertical-align: top; height:25px;">{{ number_format((float) $factura->total_amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="9" class="total" style="text-align: right;"><b>Total</b></td>
                    <td class="total"><strong>$ {{ number_format($totalFacturado, 2) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>
@endif
