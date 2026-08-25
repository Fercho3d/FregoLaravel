<?php

namespace Tests\Feature\Payments;

use App\Livewire\Payments\PaymentRequestForm;
use App\Livewire\Payments\PaymentRequestList;
use App\Models\Frego\PaymentByTransaction;
use App\Models\Frego\PaymentRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\FregoSchema;
use Tests\TestCase;

/**
 * Alta de solicitudes de pago y su ciclo de vida.
 *
 * Corre sobre el esquema de pruebas porque escribe. El escenario cabe en la
 * cabeza: un proveedor con dos costos en pesos y un tercero con uno en dólares,
 * para poder comprobar las dos reglas que impiden agruparlos.
 */
class PaymentRequestFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FregoSchema::create();
        $this->seedFixture();

        Http::preventStrayRequests();
        Http::fake(['sidofqa.segob.gob.mx/*' => Http::response(['ListaIndicadores' => []])]);
    }

    private function seedFixture(): void
    {
        DB::table('account')->insert([
            ['account_id' => 1, 'account_name' => 'Pesos', 'default' => 1, 'prefix' => 'MXN'],
            ['account_id' => 2, 'account_name' => 'Dólares', 'default' => null, 'prefix' => 'USD'],
        ]);

        DB::table('exchange')->insert([
            ['exchange_id' => 1, 'exchange_value' => 1, 'date_exchange' => '2026-01-15', 'account' => 1],
            ['exchange_id' => 2, 'exchange_value' => 20, 'date_exchange' => '2026-01-15', 'account' => 2],
        ]);

        DB::table('bank')->insert([['bank_id' => 1, 'bank_name' => 'BBVA MXN', 'active' => 1]]);
        DB::table('booking')->insert([['booking_id' => 1, 'booking_number' => 'BK-1', 'client' => 1, 'mode' => 10]]);
        DB::table('client')->insert([['client_id' => 1, 'fullName' => 'Cliente Uno']]);
        DB::table('provider')->insert([
            ['provider_id' => 1, 'fullName' => 'Proveedor Uno'],
            ['provider_id' => 2, 'fullName' => 'Proveedor Dos'],
        ]);
        DB::table('charge_type')->insert([
            ['charge_type_id' => 1, 'charge_type_name' => 'Flete', 'tax_rate' => 0, 'tax_retention' => 0, 'non_deductible' => 0],
        ]);

        // Dos costos del mismo proveedor en pesos, uno de otro proveedor y uno en dólares.
        $costos = [
            [1, 1, 1, 1000],
            [2, 1, 1, 500],
            [3, 2, 1, 700],
            [4, 1, 2, 300],
        ];

        foreach ($costos as [$id, $proveedor, $cuenta, $importe]) {
            DB::table('transaction')->insert([
                'transc_id' => $id, 'booking' => 1, 'tran_type' => 1, 'vendor' => $proveedor,
                'account' => $cuenta, 'tran_number' => "C-{$id}", 'tran_date' => '2026-01-15',
            ]);
            DB::table('charge')->insert([
                'charge_id' => $id, 'transaction' => $id, 'type' => 1, 'quantity' => 1, 'price' => $importe,
            ]);
        }
    }

    private function usuario(int $rol = User::ROLE_ADMIN): User
    {
        $usuario = new User;
        $usuario->usr_id = 7;
        $usuario->role = $rol;

        return $usuario;
    }

    /** @param  int[]  $ids */
    private function formulario(array $ids): Testable
    {
        $this->actingAs($this->usuario());

        return Livewire::withQueryParams(['ids' => implode(',', $ids)])->test(PaymentRequestForm::class);
    }

    public function test_agrupar_dos_costos_del_mismo_proveedor(): void
    {
        $this->formulario([1, 2])
            ->set('number', 'CHQ-100')
            ->set('date', '2026-01-20')
            ->set('bankId', '1')
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = PaymentRequest::first();

        $this->assertSame(2, (int) $solicitud->type, 'Un costo genera una solicitud de pago a proveedor.');
        $this->assertSame(1, (int) $solicitud->provider_id);
        $this->assertSame(1500.0, (float) $solicitud->amount);
        $this->assertSame(2, PaymentByTransaction::where('request_id', $solicitud->request_id)->count());
    }

    public function test_el_pago_queda_aplicado_a_cada_transaccion(): void
    {
        $this->formulario([1, 2])
            ->set('number', 'CHQ-100')->set('date', '2026-01-20')->set('bankId', '1')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1000.0, (float) DB::table('transaction')->where('transc_id', 1)->value('paid_amount'));
        $this->assertSame(500.0, (float) DB::table('transaction')->where('transc_id', 2)->value('paid_amount'));
    }

    public function test_no_se_agrupan_proveedores_distintos(): void
    {
        $this->formulario([1, 3])
            ->set('number', 'CHQ-100')->set('date', '2026-01-20')->set('bankId', '1')
            ->call('save')
            ->assertHasErrors('seleccion');

        $this->assertSame(0, PaymentRequest::count());
    }

    public function test_no_se_agrupan_divisas_distintas(): void
    {
        $this->formulario([1, 4])
            ->set('number', 'CHQ-100')->set('date', '2026-01-20')->set('bankId', '1')
            ->call('save')
            ->assertHasErrors('seleccion');

        $this->assertSame(0, PaymentRequest::count());
    }

    public function test_el_importe_se_puede_ajustar_renglon_por_renglon(): void
    {
        $this->formulario([1, 2])
            ->set('number', 'CHQ-100')->set('date', '2026-01-20')->set('bankId', '1')
            ->set('amounts.1', '400')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(900.0, (float) PaymentRequest::first()->amount);
        $this->assertSame(400.0, (float) PaymentByTransaction::where('transc_id', 1)->value('amount'));
    }

    public function test_sin_seleccion_la_pantalla_responde_404(): void
    {
        $this->actingAs($this->usuario());

        Livewire::withQueryParams(['ids' => ''])->test(PaymentRequestForm::class)->assertNotFound();
    }

    // ------------------------------------------------- Ciclo de vida

    private function conSolicitud(): int
    {
        $this->formulario([1, 2])
            ->set('number', 'CHQ-100')->set('date', '2026-01-20')->set('bankId', '1')
            ->call('save');

        return (int) PaymentRequest::first()->request_id;
    }

    private function listado(int $rol = User::ROLE_ADMIN): Testable
    {
        $this->actingAs($this->usuario($rol));

        return Livewire::test(PaymentRequestList::class);
    }

    public function test_marcar_como_pagada(): void
    {
        $id = $this->conSolicitud();

        $this->listado()->call('markPaid', $id);

        $this->assertSame(1, (int) PaymentRequest::find($id)->paid);
    }

    public function test_una_solicitud_pagada_no_se_vuelve_a_pagar(): void
    {
        $id = $this->conSolicitud();

        $this->listado()->call('markPaid', $id);
        $this->listado()->call('markPaid', $id)->assertStatus(422);
    }

    public function test_reabrir_una_solicitud_pagada(): void
    {
        $id = $this->conSolicitud();

        $this->listado()->call('markPaid', $id);
        $this->listado()->call('reopen', $id);

        $this->assertSame(0, (int) PaymentRequest::find($id)->paid);
    }

    /** Una solicitud borrada no puede seguir pagando: sus renglones se van con ella. */
    public function test_borrar_una_solicitud_suelta_sus_transacciones(): void
    {
        $id = $this->conSolicitud();

        $this->listado()->call('delete', $id);

        $this->assertSame(0, PaymentRequest::count());
        $this->assertSame(0, PaymentByTransaction::where('request_id', $id)->count());
    }

    public function test_una_solicitud_pagada_no_se_borra(): void
    {
        $id = $this->conSolicitud();

        $this->listado()->call('markPaid', $id);
        $this->listado()->call('delete', $id)->assertStatus(422);

        $this->assertSame(1, PaymentRequest::count());
    }

    public function test_quien_no_es_administrador_no_toca_las_solicitudes(): void
    {
        $id = $this->conSolicitud();

        $this->listado(User::ROLE_USER)->call('markPaid', $id)->assertForbidden();
        $this->listado(User::ROLE_USER)->call('delete', $id)->assertForbidden();

        $this->assertSame(0, (int) PaymentRequest::find($id)->paid);
    }
}
