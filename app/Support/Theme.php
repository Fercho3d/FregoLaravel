<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

/**
 * Tema visual de la interfaz. `System` sigue la preferencia del sistema
 * operativo y se resuelve en el navegador; las otras dos son explícitas.
 */
enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    /** Nombre de la cookie que recuerda el tema de visitantes sin sesión. */
    public const COOKIE = 'frego_theme';

    /** Duración de la cookie en minutos (un año). */
    public const COOKIE_MINUTES = 525_600;

    /**
     * Tema efectivo de la petición actual: preferencia guardada del usuario
     * autenticado y, si no hay sesión, la cookie del navegador.
     */
    public static function current(): self
    {
        $usuario = auth()->user();

        if ($usuario !== null) {
            return $usuario->themePreference();
        }

        return self::tryFrom((string) Cookie::get(self::COOKIE)) ?? self::System;
    }

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Claro',
            self::Dark => 'Oscuro',
            self::System => 'Sistema',
        };
    }
}
