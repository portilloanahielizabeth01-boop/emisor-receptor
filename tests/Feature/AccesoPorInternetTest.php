<?php

namespace Tests\Feature;

use App\Models\Partida;
use App\Services\ConexionReverb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Pruebas de lo que cambia al usar el juego por internet
 * (Cloudflare Tunnel o Laravel Cloud).
 */
class AccesoPorInternetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    /**
     * Simula un pedido que llega por Cloudflare Tunnel: para Laravel viene de
     * 127.0.0.1 (cloudflared corre en la misma PC), pero trae los headers que
     * agrega Cloudflare con la IP real del chico.
     *
     * @return array<string, string>
     */
    private function headersDelTunel(): array
    {
        return ['CF-Connecting-IP' => '181.10.20.30', 'X-Forwarded-For' => '181.10.20.30'];
    }

    public function test_por_el_tunel_un_alumno_no_puede_usar_los_controles(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->headersDelTunel())
            ->postJson('/tablero/iniciar', ['tablas' => [2], 'meta' => 10])
            ->assertUnauthorized();

        $this->assertSame(Partida::ESPERA, Partida::actual()->estado);
    }

    public function test_por_el_tunel_el_tablero_no_muestra_los_controles(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->headersDelTunel())
            ->get('/tablero')
            ->assertOk()
            ->assertDontSee('boton-iniciar');
    }

    public function test_la_docente_entra_con_la_clave_correcta(): void
    {
        config(['carrera.clave_docente' => 'tablas2026']);

        $this->post('/docente/entrar', ['clave' => 'tablas2026'])
            ->assertRedirect(route('tablero'))
            ->assertSessionHas('es_docente', true);
    }

    public function test_con_clave_incorrecta_no_entra(): void
    {
        config(['carrera.clave_docente' => 'tablas2026']);

        $this->post('/docente/entrar', ['clave' => 'adivinanza'])
            ->assertSessionHasErrors('clave')
            ->assertSessionMissing('es_docente');
    }

    public function test_si_no_hay_clave_configurada_nadie_puede_entrar_con_clave(): void
    {
        config(['carrera.clave_docente' => null]);

        $this->post('/docente/entrar', ['clave' => ''])->assertSessionHasErrors('clave');
        $this->post('/docente/entrar', ['clave' => 'cualquiera'])->assertSessionMissing('es_docente');
    }

    public function test_con_la_sesion_de_docente_puede_usar_los_controles_desde_internet(): void
    {
        $this->withSession(['es_docente' => true])
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->headersDelTunel())
            ->postJson('/tablero/iniciar', ['tablas' => [2, 3], 'meta' => 10])
            ->assertOk();

        $this->assertSame(Partida::JUGANDO, Partida::actual()->estado);
    }

    public function test_si_hay_clave_el_tablero_muestra_el_formulario_de_ingreso(): void
    {
        config(['carrera.clave_docente' => 'tablas2026']);

        $this->withServerVariables(['REMOTE_ADDR' => '181.10.20.30'])
            ->get('/tablero')
            ->assertOk()
            ->assertSee('Soy la docente');
    }

    public function test_en_el_aula_el_navegador_usa_la_ip_de_la_pagina(): void
    {
        config([
            'broadcasting.connections.reverb.options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http'],
            'carrera.reverb_publico' => ['host' => null, 'port' => null, 'scheme' => null],
        ]);

        $conexion = app(ConexionReverb::class)->paraNavegador();

        $this->assertNull($conexion['host']);
        $this->assertSame(8080, $conexion['port']);
        $this->assertSame('http', $conexion['scheme']);
    }

    public function test_con_el_tunel_el_navegador_usa_la_direccion_publica(): void
    {
        config([
            'broadcasting.connections.reverb.options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http'],
            'carrera.reverb_publico' => ['host' => 'ws-prueba.trycloudflare.com', 'port' => 443, 'scheme' => 'https'],
        ]);

        $conexion = app(ConexionReverb::class)->paraNavegador();

        $this->assertSame('ws-prueba.trycloudflare.com', $conexion['host']);
        $this->assertSame(443, $conexion['port']);
        $this->assertSame('https', $conexion['scheme']);
    }

    public function test_en_laravel_cloud_el_navegador_usa_el_reverb_administrado(): void
    {
        // Laravel Cloud inyecta REVERB_HOST/PORT/SCHEME apuntando a su cluster.
        config([
            'broadcasting.connections.reverb.options' => ['host' => 'ws-abc-reverb.laravel.cloud', 'port' => 443, 'scheme' => 'https'],
            'carrera.reverb_publico' => ['host' => null, 'port' => null, 'scheme' => null],
        ]);

        $conexion = app(ConexionReverb::class)->paraNavegador();

        $this->assertSame('ws-abc-reverb.laravel.cloud', $conexion['host']);
        $this->assertSame(443, $conexion['port']);
        $this->assertSame('https', $conexion['scheme']);
    }

    public function test_la_pagina_le_pasa_la_conexion_al_navegador_sin_el_secreto(): void
    {
        config([
            'broadcasting.connections.reverb.secret' => 'secreto-que-no-debe-salir',
            'carrera.reverb_publico' => ['host' => 'ws-prueba.trycloudflare.com', 'port' => 443, 'scheme' => 'https'],
        ]);

        $this->get('/alumno')
            ->assertOk()
            ->assertSee('window.REVERB', false)
            ->assertSee('ws-prueba.trycloudflare.com')
            ->assertDontSee('secreto-que-no-debe-salir');
    }

    public function test_el_qr_usa_la_direccion_publica_de_app_url(): void
    {
        config(['app.url' => 'https://app-prueba.trycloudflare.com', 'carrera.url_aula' => null]);

        $this->get('/tablero')->assertSee('https://app-prueba.trycloudflare.com/alumno');
    }
}
