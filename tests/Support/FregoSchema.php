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
        });

        Schema::create('booking', function ($table) {
            $table->integer('booking_id')->primary();
            $table->string('booking_number')->nullable();
            $table->integer('client')->nullable();
            $table->date('loading_EDT')->nullable();
            $table->integer('mode')->default(10);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
        });

        Schema::create('client', function ($table) {
            $table->integer('client_id')->primary();
            $table->string('fullName');
            $table->string('email')->nullable();
            $table->string('email_notification')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
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
