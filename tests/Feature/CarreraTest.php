<?php

namespace Tests\Feature;

use App\Events\AlumnoUnido;
use App\Events\ErrorRegistrado;
use App\Events\JuegoIniciado;
use App\Events\JuegoReiniciado;
use App\Events\MisionCumplida;
use App\Events\NuevaPregunta;
use App\Events\ResultadoRespuesta;
use App\Events\TableroActualizado;
use App\Models\Alumno;
use App\Models\Partida;
use App\Models\Respuesta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Pruebas de las reglas del juego. Event::fake() "atrapa" los eventos
 * en vez de mandarlos a Reverb, así podemos verificar qué se publicó.
 */
class CarreraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    /**
     * Deja una partida en curso con la meta indicada.
     */
    private function partidaJugando(int $meta = 50, int $totalCorrectas = 0): Partida
    {
        $partida = Partida::actual();
        $partida->update(['estado' => Partida::JUGANDO, 'meta' => $meta, 'total_correctas' => $totalCorrectas]);

        return $partida;
    }

    public function test_un_alumno_se_une_y_recibe_su_token(): void
    {
        $respuesta = $this->postJson('/alumno/unirse', ['nombre' => 'Juan', 'avatar' => '🚗']);

        $respuesta->assertCreated()->assertJsonPath('alumno.nombre', 'Juan');
        $this->assertSame(40, strlen($respuesta->json('token')));
        Event::assertDispatched(AlumnoUnido::class);
        Event::assertDispatched(TableroActualizado::class);
        // El juego todavía no empezó: no hay pregunta.
        Event::assertNotDispatched(NuevaPregunta::class);
    }

    public function test_si_se_une_con_el_juego_empezado_recibe_una_pregunta(): void
    {
        $this->partidaJugando();

        $this->postJson('/alumno/unirse', ['nombre' => 'Ana', 'avatar' => '🦄'])->assertCreated();

        Event::assertDispatched(NuevaPregunta::class);
    }

    public function test_no_acepta_un_avatar_que_no_esta_en_la_lista(): void
    {
        $this->postJson('/alumno/unirse', ['nombre' => 'Juan', 'avatar' => '💣'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_sin_token_no_se_puede_responder(): void
    {
        $this->postJson('/alumno/responder', ['a' => 4, 'b' => 5, 'respuesta' => 20])
            ->assertUnauthorized();
    }

    public function test_al_recargar_recupera_la_misma_pregunta_y_su_progreso(): void
    {
        $this->partidaJugando();
        $alumno = Alumno::factory()->conPregunta(4, 5)->create(['aciertos' => 7]);

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->getJson('/alumno/estado')
            ->assertOk()
            ->assertJsonPath('alumno.aciertos', 7)
            ->assertJsonPath('pregunta.a', 4)
            ->assertJsonPath('pregunta.b', 5);
    }

    public function test_respuesta_correcta_suma_y_trae_nueva_pregunta(): void
    {
        $partida = $this->partidaJugando();
        $alumno = Alumno::factory()->conPregunta(4, 5)->create();

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 4, 'b' => 5, 'respuesta' => 20])
            ->assertAccepted();

        $this->assertSame(1, $alumno->fresh()->aciertos);
        $this->assertSame(1, $partida->fresh()->total_correctas);
        Event::assertDispatched(ResultadoRespuesta::class, fn ($evento) => $evento->esCorrecta && $evento->respuestaCorrecta === 20);
        Event::assertDispatched(NuevaPregunta::class, fn ($evento) => $evento->alumnoId === $alumno->id);
        Event::assertNotDispatched(ErrorRegistrado::class);
        $this->assertDatabaseHas('respuestas', ['alumno_id' => $alumno->id, 'es_correcta' => true]);
    }

    public function test_respuesta_incorrecta_marca_el_error_y_pasa_a_otra_tabla(): void
    {
        $this->partidaJugando()->update(['tablas' => [3, 7]]);
        $alumno = Alumno::factory()->conPregunta(3, 4, [12, 7, 15, 16])->create(['aciertos' => 2]);

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 3, 'b' => 4, 'respuesta' => 7])
            ->assertAccepted();

        $alumno->refresh();
        $this->assertSame(2, $alumno->aciertos, 'No se le resta nada');
        $this->assertSame(7, $alumno->pregunta['a'], 'Sigue con otra tabla');
        Event::assertDispatched(
            ResultadoRespuesta::class,
            fn ($evento) => ! $evento->esCorrecta && $evento->respuestaElegida === 7 && $evento->respuestaCorrecta === 12,
        );
        Event::assertDispatched(
            NuevaPregunta::class,
            fn ($evento) => $evento->alumnoId === $alumno->id && $evento->pregunta['a'] === 7,
        );
        $this->assertDatabaseHas('respuestas', [
            'alumno_id' => $alumno->id, 'factor_a' => 3, 'factor_b' => 4, 'respuesta_elegida' => 7, 'es_correcta' => false,
        ]);
    }

    public function test_el_error_le_llega_a_la_docente_con_la_tabla_y_la_operacion(): void
    {
        $this->partidaJugando();
        $alumno = Alumno::factory()->conPregunta(3, 4, [12, 7, 15, 16])->create(['nombre' => 'Juan']);

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 3, 'b' => 4, 'respuesta' => 7])
            ->assertAccepted();

        Event::assertDispatched(ErrorRegistrado::class, function ($evento) use ($alumno) {
            $error = $evento->error;

            return $evento->alumno['id'] === $alumno->id
                && $evento->alumno['nombre'] === 'Juan'
                && [$error['a'], $error['b'], $error['respuesta_elegida'], $error['respuesta_correcta']] === [3, 4, 7, 12];
        });
    }

    public function test_el_aviso_de_error_es_chico_aunque_haya_muchos_errores_guardados(): void
    {
        $this->partidaJugando();
        $alumno = Alumno::factory()->conPregunta(3, 4, [12, 7, 15, 16])->create();
        $alumno->respuestas()->createMany(array_fill(0, 500, [
            'factor_a' => 7, 'factor_b' => 8, 'respuesta_elegida' => 54, 'es_correcta' => false,
        ]));

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 3, 'b' => 4, 'respuesta' => 7])
            ->assertAccepted();

        // Reverb rechaza mensajes de más de 10 KB (reverb.servers.reverb.max_request_size).
        Event::assertDispatched(
            ErrorRegistrado::class,
            fn ($evento) => strlen(json_encode($evento->broadcastWith())) < 1000,
        );
    }

    public function test_el_reporte_lista_solo_los_ultimos_errores_de_cada_alumno(): void
    {
        $alumno = Alumno::factory()->create();
        $alumno->respuestas()->createMany(array_fill(0, Respuesta::ERRORES_POR_ALUMNO + 5, [
            'factor_a' => 7, 'factor_b' => 8, 'respuesta_elegida' => 54, 'es_correcta' => false,
        ]));

        $this->getJson('/tablero/errores')
            ->assertOk()
            ->assertJsonPath('total', Respuesta::ERRORES_POR_ALUMNO + 5)
            ->assertJsonPath('por_alumno.0.total_errores', Respuesta::ERRORES_POR_ALUMNO + 5)
            ->assertJsonCount(Respuesta::ERRORES_POR_ALUMNO, 'por_alumno.0.errores');
    }

    public function test_la_docente_consulta_el_reporte_de_errores(): void
    {
        $juan = Alumno::factory()->create(['nombre' => 'Juan']);
        $ana = Alumno::factory()->create(['nombre' => 'Ana']);
        $juan->respuestas()->createMany([
            ['factor_a' => 7, 'factor_b' => 8, 'respuesta_elegida' => 54, 'es_correcta' => false],
            ['factor_a' => 7, 'factor_b' => 6, 'respuesta_elegida' => 48, 'es_correcta' => false],
            ['factor_a' => 2, 'factor_b' => 5, 'respuesta_elegida' => 10, 'es_correcta' => true],
        ]);
        $ana->respuestas()->create(['factor_a' => 4, 'factor_b' => 3, 'respuesta_elegida' => 7, 'es_correcta' => false]);

        $this->getJson('/tablero/errores')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('por_tabla', [['tabla' => 7, 'errores' => 2], ['tabla' => 4, 'errores' => 1]])
            ->assertJsonPath('por_alumno.0.nombre', 'Juan')
            ->assertJsonCount(2, 'por_alumno.0.errores')
            ->assertJsonPath('por_alumno.0.errores.0.a', 7)
            ->assertJsonPath('por_alumno.0.errores.0.b', 6)
            ->assertJsonPath('por_alumno.0.errores.0.respuesta_correcta', 42)
            ->assertJsonPath('por_alumno.1.nombre', 'Ana');
    }

    public function test_una_tablet_no_puede_ver_el_reporte_de_errores(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.0.50'])
            ->getJson('/tablero/errores')
            ->assertUnauthorized();
    }

    public function test_el_cliente_no_puede_inventar_una_pregunta_facil(): void
    {
        $this->partidaJugando();
        // En el servidor la pregunta es 7 × 8...
        $alumno = Alumno::factory()->conPregunta(7, 8, [56, 54, 63, 64])->create();

        // ...pero el cliente tramposo dice que respondió "1 × 1 = 1".
        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 1, 'b' => 1, 'respuesta' => 1])
            ->assertConflict()
            ->assertJsonPath('resultado', 'pregunta_vieja');

        $this->assertSame(0, $alumno->fresh()->aciertos);
        Event::assertNotDispatched(ResultadoRespuesta::class);
    }

    public function test_no_se_puede_responder_si_el_juego_no_empezo(): void
    {
        $alumno = Alumno::factory()->conPregunta(4, 5)->create();

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 4, 'b' => 5, 'respuesta' => 20])
            ->assertConflict()
            ->assertJsonPath('resultado', 'juego_no_activo');
    }

    public function test_al_llegar_a_la_meta_se_cumple_la_mision(): void
    {
        $partida = $this->partidaJugando(meta: 10, totalCorrectas: 9);
        $alumno = Alumno::factory()->conPregunta(4, 5)->create();

        $this->withHeader('X-Alumno-Token', $alumno->token)
            ->postJson('/alumno/responder', ['a' => 4, 'b' => 5, 'respuesta' => 20])
            ->assertAccepted();

        $this->assertSame(Partida::TERMINADA, $partida->fresh()->estado);
        Event::assertDispatchedTimes(MisionCumplida::class, 1);
        // Ya no se reparten más preguntas.
        Event::assertNotDispatched(NuevaPregunta::class);
        $this->assertNull($alumno->fresh()->pregunta);
    }

    public function test_una_tablet_no_puede_usar_los_controles_de_la_docente(): void
    {
        // Simulamos un pedido que viene de otra IP de la red (una tablet).
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.0.50'])
            ->postJson('/tablero/iniciar', ['tablas' => [2, 3], 'meta' => 20])
            ->assertUnauthorized();

        $this->assertSame(Partida::ESPERA, Partida::actual()->estado);
    }

    public function test_la_docente_inicia_el_juego_y_cada_alumno_recibe_su_pregunta(): void
    {
        Alumno::factory()->count(3)->create(['aciertos' => 5]);

        // Los tests salen por defecto desde 127.0.0.1, como la compu de la docente.
        $this->postJson('/tablero/iniciar', ['tablas' => [3, 2], 'meta' => 20])->assertOk();

        $partida = Partida::actual();
        $this->assertSame(Partida::JUGANDO, $partida->estado);
        $this->assertSame([2, 3], $partida->tablas);
        $this->assertSame(0, Alumno::query()->sum('aciertos'));
        Event::assertDispatched(JuegoIniciado::class);
        Event::assertDispatchedTimes(NuevaPregunta::class, 3);
    }

    public function test_reiniciar_vaciando_la_pista_borra_a_los_alumnos(): void
    {
        $this->partidaJugando();
        Alumno::factory()->count(2)->create();

        $this->postJson('/tablero/reiniciar', ['vaciar_pista' => true])->assertOk();

        $this->assertSame(0, Alumno::query()->count());
        $this->assertSame(Partida::ESPERA, Partida::actual()->estado);
        Event::assertDispatched(JuegoReiniciado::class, fn ($evento) => $evento->pistaVaciada);
    }

    public function test_el_tablero_de_la_docente_muestra_el_reporte_de_errores(): void
    {
        $this->get('/tablero')->assertOk()->assertSee('Errores para revisar');
    }

    public function test_el_tablero_abierto_desde_una_tablet_no_muestra_el_reporte(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.0.50'])
            ->get('/tablero')
            ->assertOk()
            ->assertDontSee('Errores para revisar');
    }
}
