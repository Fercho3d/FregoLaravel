<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Avisos de tareas del booking sin marcar, una vez al día.
 *
 * En Yii2 esto lo disparaba un cron externo llamando a dos direcciones web. Aquí
 * son dos comandos: primero los que todavía tienen tiempo y luego los que ya se
 * pasaron de fecha, con cinco minutos de diferencia para que los correos no
 * lleguen mezclados.
 *
 * La hora es la de la operación, no la del servidor —que corre en UTC—, para que
 * los avisos caigan a primera hora del día laboral.
 *
 * En el servidor hace falta la línea de cron que despierta al planificador:
 *   * * * * * cd /var/www/html/frego-laravel && php8.4 artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('frego:avisos-continuidad aviso')
    ->dailyAt('07:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->description('Tareas del booking que aún tienen tiempo');

Schedule::command('frego:avisos-continuidad vencido')
    ->dailyAt('07:05')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->description('Tareas del booking que ya se pasaron de fecha');
