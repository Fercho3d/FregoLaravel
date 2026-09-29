<?php

namespace App\Support\Diagnostico;

use Illuminate\Http\Request;
use Throwable;

/**
 * Quién, dónde y qué botón: lo que hace falta para entender un error o una
 * espera sin tener que preguntarle al usuario.
 *
 * Con Livewire todas las acciones llegan a la MISMA dirección
 * (`/livewire-…/update`), así que la URL sola no dice nada; lo que sirve es la
 * pantalla de origen (`Referer`) y el componente y método que se llamaron.
 * No se copia nada de lo que el usuario capturó: solo nombres.
 */
class ContextoPeticion
{
    /** @return array<string, mixed> */
    public static function describir(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? request() : null;

        if ($request === null || app()->runningInConsole() && ! app()->runningUnitTests()) {
            return ['origen' => 'consola: '.implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 3))];
        }

        $usuario = $request->user();

        return array_filter([
            'peticion' => $request->method().' '.$request->fullUrl(),
            'pantalla' => $request->headers->get('referer'),
            'livewire' => self::livewire($request),
            'usuario' => $usuario ? $usuario->getAuthIdentifier().' '.($usuario->username ?? '') : 'sin sesión',
            'ip' => $request->ip(),
            'navegador' => $request->userAgent(),
        ]);
    }

    /** «booking-detail → confirm», uno por componente de la petición. */
    private static function livewire(Request $request): ?string
    {
        $componentes = $request->input('components');

        if (! is_array($componentes)) {
            return null;
        }

        try {
            $partes = array_map(function ($componente) {
                $nombre = json_decode((string) ($componente['snapshot'] ?? ''), true)['memo']['name'] ?? '?';
                $metodos = array_column((array) ($componente['calls'] ?? []), 'method');
                $cambios = array_keys((array) ($componente['updates'] ?? []));

                return $nombre.' → '.(implode(', ', [...$metodos, ...$cambios]) ?: 'repintar');
            }, $componentes);
        } catch (Throwable) {
            return null;
        }

        return implode(' | ', $partes);
    }
}
