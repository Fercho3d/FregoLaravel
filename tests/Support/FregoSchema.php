<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Reconstruye, en la conexión de pruebas, las tablas del módulo Transactions con
 * los mismos nombres y tipos que el esquema heredado de Yii2.
 *
 * Sirve para probar la aritmética con datos diminutos hechos a mano, donde el
 * resultado esperado se puede calcular a lápiz. Las pruebas de paridad, en cambio,
 * corren contra la base real; las dos cosas se complementan.
 */
class FregoSchema
{
    /**
     * Tabla `users` heredada de Yii2. Va aparte porque la mayoría de las pruebas
     * no la necesita: para autenticar basta un modelo en memoria.
     */
    public static function createUsers(): void
    {
        Schema::create('users', function ($table) {
            $table->increments('usr_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->tinyInteger('role')->nullable();
            $table->tinyInteger('access')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('provider_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('remember_token', 100)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
        });
    }

    public static function create(): void
    {
        // No es del esquema heredado, pero cualquier prueba que pinte una
        // pantalla la necesita: el layout consulta ahí el tema del usuario.
        Schema::create('user_preferences', function ($table) {
            $table->id();
            $table->unsignedInteger('usr_id')->unique();
            $table->string('theme', 10)->default('system');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('account', function ($table) {
            $table->integer('account_id')->primary();
            $table->string('account_name')->nullable();
            $table->integer('default')->nullable();
            $table->string('prefix')->nullable();
        });

        Schema::create('company', function ($table) {
            $table->integer('company_id')->primary();
            $table->string('name');
            $table->string('rfc')->nullable();
            // Datos fiscales del emisor: los usa el layout CFDI.
            $table->string('business_name')->nullable();
            $table->string('regimen_fiscal')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::create('booking', function ($table) {
            $table->integer('booking_id')->primary();
            $table->string('booking_number')->nullable();
            $table->integer('client')->nullable();
            $table->date('loading_EDT')->nullable();
            $table->integer('mode')->default(10);
            // Columnas que usa el listado de operación y el portal.
            $table->integer('is_draft')->default(0);
            $table->boolean('locked')->default(false);
            $table->string('customer_reference')->nullable();
            $table->string('commodity')->nullable();
            $table->string('set_point')->nullable();
            $table->string('booking_type')->nullable();
            $table->date('dicharge_ETA')->nullable();
            $table->date('arrival')->nullable();
            $table->integer('vessel')->nullable();
            $table->integer('loading_port')->nullable();
            $table->integer('dicharge_port_id')->nullable();
            $table->integer('pick_up_place_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
        });

        // Tablas que acompañan al booking en el listado de operación.
        Schema::create('booking_continuity', function ($table) {
            $table->increments('cont_id');
            $table->integer('booking')->nullable();
            $table->dateTime('pickup_date')->nullable();
            $table->dateTime('SI_date')->nullable();
        });

        Schema::create('vessel', function ($table) {
            $table->integer('vessel_id')->primary();
            $table->string('vessel_name')->nullable();
        });

        Schema::create('loading_ports', function ($table) {
            $table->integer('port_id')->primary();
            $table->string('port_name')->nullable();
            $table->integer('deleted')->default(0);
        });

        Schema::create('dicharge_port', function ($table) {
            $table->integer('dicharge_port_id')->primary();
            $table->string('name')->nullable();
            $table->integer('deleted')->default(0);
        });

        Schema::create('pickup_place', function ($table) {
            $table->integer('pick_id')->primary();
            $table->string('name')->nullable();
        });

        Schema::create('check_list', function ($table) {
            $table->increments('check_id');
            $table->integer('booking')->nullable();

            foreach ([
                'booking_number', 'pickup_date', 'modality', 'doc_cut_of', 'SI_date', 'cleared',
                'departure', 'bl_payment', 'swb', 'vessel', 'number', 'client', 'loading_port',
                'loading_EDT', 'dicharge_port', 'container_type', 'commodity', 'set_point',
                'dicharge_ETA', 'vacuum_maneuver', 'draf_client', 'gated_IN', 'gated_out',
                'delivered', 'pick_up_place', 'insurance', 'corrected_draft', 'vgm',
            ] as $casilla) {
                $table->dateTime($casilla.'_chk_date')->nullable();
            }
        });

        Schema::create('containers', function ($table) {
            $table->increments('container_ID');
            $table->integer('booking')->nullable();
            $table->integer('container_type')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('comodity')->nullable();
            $table->string('number')->nullable();
            $table->string('seal')->nullable();
            $table->dateTime('pick_up_date')->nullable();
        });

        Schema::create('container_types', function ($table) {
            $table->integer('contType_id')->primary();
            $table->string('container_name')->nullable();
        });

        Schema::create('client', function ($table) {
            $table->integer('client_id')->primary();
            $table->string('fullName');
            $table->string('email')->nullable();
            $table->string('email_notification')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
            // Datos fiscales del receptor: los usa el layout CFDI.
            $table->string('rfc')->nullable();
            $table->string('pay_form')->nullable();
            $table->string('pay_method')->nullable();
            $table->string('invoice_use')->nullable();
            $table->string('regimen_fiscal_id')->nullable();
            $table->string('postal_code')->nullable();
        });

        Schema::create('provider', function ($table) {
            $table->integer('provider_id')->primary();
            $table->string('fullName')->nullable();
            $table->string('email')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
        });

        Schema::create('charge_type', function ($table) {
            $table->integer('charge_type_id')->primary();
            $table->string('charge_type_name')->nullable();
            $table->string('tax_name')->nullable();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_retention', 7, 4)->default(0);
            $table->string('product_code')->nullable();
            $table->boolean('non_deductible')->default(false);
            $table->boolean('deleted')->default(false);
        });

        Schema::create('service', function ($table) {
            $table->integer('service_id')->primary();
            $table->integer('charge_type_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('provider_id')->nullable();
            $table->integer('type')->nullable();
            $table->decimal('price', 16, 4)->nullable();
            $table->string('description')->nullable();
            $table->integer('active')->default(1);
        });

        Schema::create('exchange', function ($table) {
            $table->integer('exchange_id')->primary();
            $table->decimal('exchange_value', 11, 4);
            $table->date('date_exchange');
            $table->integer('account')->nullable();
        });

        Schema::create('transaction', function ($table) {
            $table->integer('transc_id')->primary();
            $table->date('tran_date')->nullable();
            $table->string('tran_number')->nullable();
            $table->integer('account')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('booking')->nullable();
            $table->integer('vendor')->nullable();
            $table->integer('customer')->nullable();
            $table->integer('tran_type')->nullable();
            // Nullable como en la base real: los costos se guardan sin tipo de
            // factura, y el motor cuenta con que la comparación contra NULL caiga
            // al ELSE del CASE.
            $table->integer('invoice_type')->nullable()->default(1);
            $table->integer('invoice')->nullable();
            $table->string('seal')->nullable();
            $table->string('new_seal')->nullable();
            $table->string('cancel_reason_id')->nullable();
            $table->string('pdf_attach')->default('');
            $table->string('xml_attach')->nullable();
            $table->integer('cancelled')->default(0);
            $table->integer('paid')->default(0);
            $table->integer('payment_request')->default(0);
            $table->integer('request_id')->nullable();
            $table->integer('bank_id')->nullable();
            $table->integer('payment_terms')->nullable();
            $table->decimal('paid_amount', 18, 4)->nullable();
            $table->decimal('custom_tc', 10, 4)->nullable();
            $table->integer('open')->default(1);
            $table->integer('active')->default(1);
            $table->dateTime('request_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('modified_by')->nullable();
        });

        Schema::create('charge', function ($table) {
            $table->integer('charge_id')->primary();
            $table->integer('transaction')->nullable();
            $table->integer('service_id')->nullable();
            $table->integer('type')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->decimal('unit', 16, 4)->nullable();
            $table->decimal('price', 16, 4)->nullable();
            $table->integer('prepaid')->nullable();
        });

        Schema::create('bank', function ($table) {
            $table->integer('bank_id')->primary();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('default')->default(false);
        });

        Schema::create('payment_request', function ($table) {
            $table->integer('request_id')->primary();
            $table->string('number')->default('');
            $table->decimal('amount', 18, 2)->default(0);
            $table->integer('paid')->default(0);
            $table->integer('provider_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('currency_id')->nullable();
            $table->integer('bank_id')->nullable();
            $table->integer('type')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('modified_by')->nullable();
            $table->integer('opened')->default(1);
            $table->integer('custom_tc')->nullable();
            $table->decimal('tc_value', 16, 4)->nullable();
            $table->decimal('total_to_pay', 18, 4)->nullable();
            $table->text('payments')->nullable();
        });

        Schema::create('payments_by_transaction', function ($table) {
            $table->integer('request_id');
            $table->integer('transc_id');
            $table->decimal('amount', 16, 4)->nullable();
            $table->integer('paid')->default(0);
            $table->primary(['request_id', 'transc_id']);
        });
    }
}
