<?php

namespace App\Http\Middleware;

use App\Support\Diagnostico\ContextoPeticion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja en el log las peticiones que tardan de más, con quién, desde qué
 * pantalla y qué botón.
 *
 * Existe porque «se quedó trabado» no dejaba rastro: el log de Apache no guarda
 * duraciones y el servidor contestaba bien, así que no había forma de saber si
 * la espera fue del servidor o del navegador.
 */
class RegistraPeticionesLentas
{
    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);

        $respuesta = $next($request);

        $ms = (int) round((microtime(true) - $inicio) * 1000);

        if ($ms >= config('logging.peticion_lenta_ms')) {
            Log::warning("Petición lenta: {$ms} ms", ContextoPeticion::describir($request));
        }

        return $respuesta;
    }
}
