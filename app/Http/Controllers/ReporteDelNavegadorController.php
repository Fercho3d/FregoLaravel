<?php

namespace App\Http\Controllers;

use App\Support\Diagnostico\ContextoPeticion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Recibe lo que el navegador ve y el servidor no: errores de JavaScript,
 * acciones que fallan y, sobre todo, acciones que se quedan sin respuesta
 * (`resources/js/app.js`, «Reporte de fallas del navegador»).
 *
 * Una acción sin respuesta es un error —el usuario se queda viendo el giro— y
 * llega por correo; lo demás solo queda en el log, porque las extensiones del
 * navegador también lanzan errores y serían ruido.
 */
class ReporteDelNavegadorController extends Controller
{
    private const TIPOS = ['js', 'fallo', 'lenta', 'sin_respuesta'];

    public function __invoke(Request $request): Response
    {
        // 422 a secas: esta ruta solo la llama `fetch`, y el manejador de
        // excepciones de la app redirige los errores de validación fuera de api/*.
        $validador = Validator::make($request->all(), [
            'tipo' => ['required', 'in:'.implode(',', self::TIPOS)],
            'mensaje' => ['required', 'string', 'max:1000'],
            'pantalla' => ['nullable', 'string', 'max:500'],
            'accion' => ['nullable', 'string', 'max:500'],
            'ms' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validador->fails()) {
            return response()->noContent(422);
        }

        $datos = $validador->validated();

        $contexto = [
            ...ContextoPeticion::describir($request),
            // La pantalla y la acción que dice el navegador mandan sobre las de
            // esta petición, que es solo el aviso.
            'pantalla' => $datos['pantalla'] ?? null,
            'livewire' => $datos['accion'] ?? null,
            'ms' => $datos['ms'] ?? null,
        ];
        unset($contexto['peticion']);

        Log::log(
            $datos['tipo'] === 'sin_respuesta' ? 'error' : 'warning',
            'Navegador ('.$datos['tipo'].'): '.$datos['mensaje'],
            array_filter($contexto, fn ($v) => $v !== null),
        );

        return response()->noContent();
    }
}
