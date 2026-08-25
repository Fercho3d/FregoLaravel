<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz no es una página: manda al login o al tablero según la sesión.
     * (La prueba que traía Laravel esperaba un 200 y fallaba desde el andamiaje.)
     */
    public function test_la_raiz_manda_al_login_cuando_no_hay_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
