<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
