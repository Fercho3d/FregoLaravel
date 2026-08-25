<?php

use App\Http\Controllers\PortalFileController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TransactionFileController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsInternal;
use App\Http\Middleware\EnsureUserIsPortal;
use App\Livewire\Catalogs\CatalogManager;
use App\Livewire\Operations\BookingDetail;
use App\Livewire\Operations\BookingForm;
use App\Livewire\Operations\BookingList;
use App\Livewire\Payments\PaymentRequestForm;
use App\Livewire\Payments\PaymentRequestList;
use App\Livewire\Payments\PaymentsReport;
use App\Livewire\Portal\PortalHome;
use App\Livewire\Transactions\BookingReport;
use App\Livewire\Transactions\TransactionDetail;
use App\Livewire\Transactions\TransactionForm;
use App\Livewire\Transactions\TransactionTable;
use App\Livewire\Users\UserManager;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    // Cada cuenta a su sitio: el personal al sistema, el cliente y el proveedor
    // a su portal.
    return redirect()->route(auth()->user()->isPortal() ? 'portal' : 'dashboard');
});

// Tema claro/oscuro. Sin `auth` a propósito: la pantalla de acceso también
// deja elegirlo (se recuerda por cookie hasta que haya sesión).
Route::put('/preferencias/tema', ThemeController::class)->name('preferences.theme');

// Seguridad de la cuenta (contraseña + 2FA). Va fuera de los dos bloques porque
// es de cualquiera con sesión, incluidas las cuentas de portal. Las acciones las
// expone Laravel Fortify.
Route::view('/seguridad', 'security.show')->middleware('auth')->name('security.show');

/*
 * Portal de clientes y proveedores. Cada cuenta ve únicamente sus documentos y,
 * si es cliente, sus embarques.
 */
Route::middleware(['auth', EnsureUserIsPortal::class])->prefix('portal')->group(function () {
    Route::get('/', PortalHome::class)->name('portal');
    Route::get('/documento/{transaction}/{kind}', PortalFileController::class)
        ->whereNumber('transaction')->name('portal.file');
});

/*
 * Sistema interno. `EnsureUserIsInternal` deja fuera a las cuentas de portal:
 * sin esa puerta verían la operación completa de la empresa.
 */
Route::middleware(['auth', EnsureUserIsInternal::class])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    /*
     * Operación: los embarques y lo que cuelga de ellos.
     */
    Route::prefix('operacion')->name('operations.')->group(function () {
        Route::get('/bookings', BookingList::class)->name('bookings');
        Route::get('/bookings/nuevo', BookingForm::class)->name('bookings.create');
        Route::get('/bookings/{booking}/editar', BookingForm::class)->whereNumber('booking')->name('bookings.edit');
        Route::get('/bookings/{booking}', BookingDetail::class)->whereNumber('booking')->name('bookings.show');
    });

    /*
     * Usuarios y accesos. Solo el super administrador entra aquí, igual que el
     * `UserController` de Yii2.
     */
    Route::get('/usuarios', UserManager::class)->name('users');

    /*
     * Catálogos maestros. Una sola pantalla para los dieciséis: lo que cambia
     * entre ellos son los campos, y esos viven en `CatalogRegistry`.
     */
    Route::middleware(EnsureUserIsAdmin::class)
        ->get('/catalogos/{catalog}', CatalogManager::class)
        ->name('catalogs.show');

    /*
     * Reportes de cobros y pagos. Viven aparte de las transacciones porque su
     * origen es otro: la tabla de solicitudes de pago, no la de documentos.
     */
    Route::middleware(EnsureUserIsAdmin::class)->prefix('pagos')->name('payments.')->group(function () {
        Route::get('/solicitudes', PaymentRequestList::class)->name('requests');
        Route::get('/solicitudes/nueva', PaymentRequestForm::class)->name('requests.create');
        Route::get('/reporte/clientes', PaymentsReport::class)->defaults('mode', 'customer')->name('report.customer');
        Route::get('/reporte/proveedores', PaymentsReport::class)->defaults('mode', 'vendor')->name('report.vendor');
        Route::get('/reporte/general', PaymentsReport::class)->defaults('mode', 'general')->name('report.general');
    });

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
