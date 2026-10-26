{{-- Campo de rango de fechas con calendario (flatpickr, ver `dateRangePicker` en
     app.js). Va en `wire:ignore` para que el repintado de Livewire no lo pise.
     `live`: filtra al elegir; sin él espera al botón «Filtrar». --}}
@props(['model', 'value' => '', 'live' => false, 'placeholder' => null])

<div wire:ignore x-data="dateRangePicker(@js($value), @js([
    'hoy' => __('Hoy'), 'ayer' => __('Ayer'), 'semana' => __('Últimos 7 días'),
    'treinta' => __('Últimos 30 días'), 'mes' => __('Este mes'), 'mesPasado' => __('Mes pasado'),
    'anio' => __('Este año'), 'anioPasado' => __('Año pasado'),
]), @js($model), @js((bool) $live))">
    <input type="text" x-ref="input" value="{{ $value }}" autocomplete="off"
           class="field-input mt-1 py-1.5 text-sm bg-panel"
           placeholder="{{ $placeholder ?? __('Todos los años') }}" title="dd/mm/aaaa - dd/mm/aaaa">
</div>
