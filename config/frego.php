<?php

/**
 * Datos de la empresa que el sistema usa al mandar correo.
 *
 * En Yii2 estaban escritos dentro de los controladores, cada uno por su cuenta:
 * el remitente en cuatro sitios y las direcciones de aviso en dos. Aquí viven en
 * un solo lugar y se pueden cambiar por `.env` sin tocar código.
 */
return [

    /** Remitente de los correos del sistema. */
    'remitente' => [
        'direccion' => env('FREGO_MAIL_FROM', 'facturas@frego.com.mx'),
        'nombre' => env('FREGO_MAIL_FROM_NAME', 'Sistema Frego'),
    ],

    /**
     * Copia oculta de las facturas que se mandan al cliente, y a quién se le
     * mandan mientras el timbrado está en pruebas (`TIMBRADO_PRODUCCION=false`):
     * ahí el cliente no debe recibir nada.
     */
    'copia_facturas' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FREGO_MAIL_FACTURAS_BCC', 'hector.torres@frego.com.mx')),
    ))),

    /** A dónde llegan los avisos de tareas atrasadas de operación. */
    'avisos_operacion' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FREGO_MAIL_AVISOS', 'soporte@juancker.com')),
    ))),
];
