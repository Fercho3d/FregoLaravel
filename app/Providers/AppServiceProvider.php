<?php

namespace App\Providers;

use App\Support\Cfdi\FacturacionModernaClient;
use App\Support\Cfdi\PacClient;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El PAC se resuelve por interfaz para poder sustituirlo en pruebas: nada
        // de lo que se prueba debe salir a la red, y un timbrado de prueba contra
        // el PAC real consume folios.
        $this->app->bind(PacClient::class, FacturacionModernaClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paginador propio: usa los tokens de tema en lugar de los grises fijos
        // de la vista que trae Laravel, que solo se ven bien en tema claro.
        // Ojo: los componentes Livewire NO heredan esto — cada uno declara su
        // `paginationView()`, porque Livewire vuelve a fijar el valor al pintar.
        Paginator::defaultView('vendor.pagination.frego');
        Paginator::defaultSimpleView('vendor.pagination.frego');
    }
}
