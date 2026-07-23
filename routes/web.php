<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Seguridad de la cuenta (contraseña + 2FA). Las acciones (activar/confirmar/
    // desactivar 2FA, cambiar contraseña) las expone Laravel Fortify.
    Route::view('/seguridad', 'security.show')->name('security.show');
});
