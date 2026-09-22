<?php

namespace Tests\Feature\Operations;

use App\Livewire\Operations\BookingDetail;
use App\Livewire\Operations\BookingList;
use App\Models\Core\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CoreSchema;
use Tests\TestCase;

/**
 * Los filtros nuevos del listado: tipo (importación / exportación) y
 * borradores. Con datos hechos a mano; la paridad contra la base real está en
 * `BookingListTest`.
 */
class BookingListFiltrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CoreSchema::create();
        CoreSchema::createUsers();

        DB::table('client')->insert([['client_id' => 1, 'fullName' => 'Cliente Uno']]);
        DB::table('booking')->insert([
            ['booking_id' => 1, 'booking_number' => 'IMP-1', 'client' => 1, 'mode' => 10, 'is_draft' => 0, 'booking_type' => Booking::TYPE_IMPORT, 'created_at' => now()],
            ['booking_id' => 2, 'booking_number' => 'EXP-1', 'client' => 1, 'mode' => 10, 'is_draft' => 0, 'booking_type' => Booking::TYPE_EXPORT, 'created_at' => now()],
            ['booking_id' => 3, 'booking_number' => 'BORRADOR-1', 'client' => 1, 'mode' => 10, 'is_draft' => 1, 'booking_type' => Booking::TYPE_EXPORT, 'created_at' => now()],
        ]);
    }

    private function listado(): Testable
    {
        $this->actingAs(User::create([
            'username' => 'operadora', 'password' => 'secreto-de-prueba', 'role' => User::ROLE_USER, 'status' => 1,
        ]));

        return Livewire::test(BookingList::class);
    }

    /** @return list<string> */
    private function numeros(Testable $listado): array
    {
        return collect($listado->viewData('filas')->items())->pluck('booking_number')->sort()->values()->all();
    }

    public function test_por_omision_no_salen_los_borradores(): void
    {
        $this->assertSame(['EXP-1', 'IMP-1'], $this->numeros($this->listado()));
    }

    public function test_el_filtro_de_borradores_solo_trae_borradores(): void
    {
        $this->assertSame(['BORRADOR-1'], $this->numeros($this->listado()->set('drafts', '1')));
    }

    public function test_el_filtro_de_tipo_separa_importaciones_de_exportaciones(): void
    {
        $this->assertSame(
            [['IMP-1'], ['EXP-1']],
            [$this->numeros($this->listado()->set('bookingType', '1')), $this->numeros($this->listado()->set('bookingType', '2'))],
        );
    }

    public function test_el_listado_ensena_el_tipo_y_los_botones_de_alta(): void
    {
        $this->listado()
            ->assertSeeInOrder(['Importación', 'Exportación'])
            ->assertSee('Nueva importación')
            ->assertSee('Nueva exportación');
    }

    public function test_en_cotizaciones_el_boton_es_de_cotizacion(): void
    {
        $this->listado()->set('mode', '9')->assertSee('Nueva cotización')->assertDontSee('Nueva importación');
    }

    /** Un borrador se abre en el detalle: ahí se le capturan contenedores y se confirma. */
    public function test_el_detalle_abre_un_borrador(): void
    {
        $this->actingAs(User::create([
            'username' => 'jefa', 'password' => 'secreto-de-prueba', 'role' => User::ROLE_ADMIN, 'status' => 1,
        ]));

        Livewire::test(BookingDetail::class, ['booking' => 3])
            ->assertOk()
            ->assertSee('Este booking es un borrador.')
            ->assertSee('Confirmar booking')
            ->assertSee('Copiar');
    }
}
