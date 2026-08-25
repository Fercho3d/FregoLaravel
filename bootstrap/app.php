<?php

use App\Http\Middleware\SetLocale;
use App\Support\Locale;
use App\Support\Theme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Las cookies del tema y del idioma no llevan datos sensibles, y una de
        // ellas la escribe el propio navegador, así que van sin cifrar.
        $middleware->encryptCookies(except: [Theme::COOKIE, Theme::RESOLVED_COOKIE, Locale::COOKIE]);

        // El idioma se resuelve después de la sesión: la preferencia del usuario
        // vive en la base y hace falta saber quién entra.
        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
