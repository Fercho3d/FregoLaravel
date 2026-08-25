<?php

use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TransactionFileController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Livewire\Transactions\BookingReport;
use App\Livewire\Transactions\TransactionDetail;
use App\Livewire\Transactions\TransactionForm;
use App\Livewire\Transactions\TransactionTable;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Tema claro/oscuro. Sin `auth` a propósito: la pantalla de acceso también
// deja elegirlo (se recuerda por cookie hasta que haya sesión).
Route::put('/preferencias/tema', ThemeController::class)->name('preferences.theme');

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Seguridad de la cuenta (contraseña + 2FA). Las acciones (activar/confirmar/
    // desactivar 2FA, cambiar contraseña) las expone Laravel Fortify.
    Route::view('/seguridad', 'security.show')->name('security.show');

    /*
     * Módulo Transactions. Los cuatro listados son el mismo componente Livewire
     * con distinto filtro base, igual que las acciones invoice / bill / all /
     * index del TransactionController de Yii2.
     *
     * Solo administradores, igual que el AccessControl del controlador original:
     * la facturación no la ven clientes, proveedores ni operación.
     */
    Route::middleware(EnsureUserIsAdmin::class)->prefix('transacciones')->name('transactions.')->group(function () {
        Route::get('/', TransactionTable::class)->name('invoice');
        Route::get('/costos', TransactionTable::class)->defaults('screen', 'bill')->name('bill');
        Route::get('/todas', TransactionTable::class)->defaults('screen', 'all')->name('all');
        Route::get('/booking/{booking}', TransactionTable::class)->defaults('screen', 'booking')->name('booking');
        Route::get('/nueva', TransactionForm::class)->name('create');
        Route::get('/reporte/booking', BookingReport::class)->name('report.booking');
        Route::get('/{transaction}', TransactionDetail::class)->whereNumber('transaction')->name('show');
        Route::get('/{transaction}/editar', TransactionForm::class)->whereNumber('transaction')->name('edit');
        Route::get('/{transaction}/archivo/{kind}', TransactionFileController::class)
            ->whereNumber('transaction')->name('file');
    });
});
