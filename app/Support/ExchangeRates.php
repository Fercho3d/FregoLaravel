<?php

namespace App\Support;

use App\Models\Core\Exchange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alta del tipo de cambio del día, tal como lo hace `Exchange::check()` en Yii2.
 *
 * Fuente: la serie SF60653 del SIE de Banxico (dólar FIX por fecha de
 * liquidación). Su valor para el día X es exactamente el que el sistema tomaba
 * del DOF para X: el último publicado **antes** de X. Esa es la convención
 * contable del sistema; comprobado fecha por fecha contra el indicador 158 del
 * DOF, que se dejó de usar cuando su certificado venció (19/09/2026).
 *
 * Si el servicio no responde, el error se registra y se sigue: guardar una
 * transacción no puede depender de que Banxico esté disponible, igual que en el
 * sistema original.
 */
class ExchangeRates
{
    /** Serie de Banxico: tipo de cambio USD FIX por fecha de liquidación. */
    private const SERIE_USD_FIX = 'SF60653';

    /** Cuenta a la que pertenece ese tipo de cambio. */
    private const CUENTA_USD = 2;

    private const TIEMPO_LIMITE = 8;

    /** @return bool Si al terminar hay un tipo de cambio registrado para la fecha. */
    public function ensureFor(Carbon $fecha): bool
    {
        if (Exchange::whereDate('date_exchange', $fecha)->exists()) {
            return true;
        }

        // Fechas futuras: todavía no hay nada publicado que registrar.
        if ($fecha->isAfter(Carbon::tomorrow())) {
            return false;
        }

        try {
            return $this->fetchAndStore($fecha);
        } catch (Throwable $e) {
            Log::warning('No se pudo obtener el tipo de cambio de Banxico', [
                'fecha' => $fecha->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function fetchAndStore(Carbon $fecha): bool
    {
        $url = sprintf(
            'https://www.banxico.org.mx/SieAPIRest/service/v1/series/%s/datos/%s/%s',
            self::SERIE_USD_FIX,
            $fecha->toDateString(),
            $fecha->toDateString(),
        );

        $dato = Http::timeout(self::TIEMPO_LIMITE)
            ->withHeaders(['Bmx-Token' => (string) config('services.banxico.token')])
            ->get($url)
            ->throw()
            ->json('bmx.series.0.datos.0');

        if (! is_numeric($dato['dato'] ?? null)) {
            return false;
        }

        Exchange::create([
            'exchange_value' => $dato['dato'],
            'date_exchange' => $fecha->toDateString(),
            'taken_date' => Carbon::createFromFormat('d/m/Y', $dato['fecha'])->toDateString(),
            'account' => self::CUENTA_USD,
            'url' => $url,
        ]);

        return true;
    }
}
