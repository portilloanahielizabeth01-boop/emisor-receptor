<?php

namespace App\Services;

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
use Illuminate\Support\Facades\DB;

/**
 * Reglas de "La Carrera de Multiplicar".
 *
 * Los controladores reciben los pedidos HTTP y llaman a estos métodos.
 * Cada método cambia la base de datos y después PUBLICA eventos con
 * broadcast(): Laravel se los pasa a Reverb y Reverb los empuja por
 * WebSocket a todos los navegadores suscritos al canal.
 */
class Carrera
{
    /**
     * Resultados posibles de responder().
     */
    public const RESPUESTA_PROCESADA = 'procesada';

    public const JUEGO_NO_ACTIVO = 'juego_no_activo';

    public const PREGUNTA_VIEJA = 'pregunta_vieja';

    public function __construct(private GeneradorPreguntas $generador) {}

    /**
     * UNIRSE: crea al alumno y avisa al proyector.
     */
    public function unirAlumno(string $nombre, string $avatar): Alumno
    {
        $alumno = Alumno::crearConToken($nombre, $avatar);
        $partida = Partida::actual();

        broadcast(new AlumnoUnido($alumno->datosPublicos()));

        // Si llegó tarde y el juego ya empezó, le damos su primera pregunta.
        if ($partida->estaJugando()) {
            $this->asignarNuevaPregunta($alumno, $partida);
        }

        $this->publicarTablero($partida);

        return $alumno;
    }

    /**
     * Devuelve la pregunta que el alumno debería tener en pantalla.
     * Se usa al recargar la página: los mensajes de WebSocket que se
     * perdieron mientras estaba desconectado NO se guardan en ningún lado,
     * así que el estado se recupera desde la base de datos.
     *
     * @return array{a: int, b: int, opciones: array<int, int>}|null
     */
    public function preguntaActualDe(Alumno $alumno, Partida $partida): ?array
    {
        if (! $partida->estaJugando()) {
            return null;
        }

        return $alumno->pregunta ?? $this->asignarNuevaPregunta($alumno, $partida);
    }

    /**
     * ENVIAR_RESPUESTA: valida la opción elegida CONTRA LA PREGUNTA GUARDADA
     * EN EL SERVIDOR y publica el resultado.
     *
     * El cliente manda "a" y "b" solo para confirmar a qué pregunta está
     * respondiendo (por ejemplo, si tocó dos veces muy rápido). La cuenta
     * se hace siempre con los datos de la base, nunca con los del cliente.
     *
     * @return string Una de las constantes RESPUESTA_PROCESADA, JUEGO_NO_ACTIVO o PREGUNTA_VIEJA.
     */
    public function responder(Alumno $alumno, int $a, int $b, int $respuestaElegida): string
    {
        $partida = Partida::actual();

        if (! $partida->estaJugando()) {
            return self::JUEGO_NO_ACTIVO;
        }

        $pregunta = $alumno->pregunta;

        if ($pregunta === null || $pregunta['a'] !== $a || $pregunta['b'] !== $b) {
            return self::PREGUNTA_VIEJA;
        }

        $esCorrecta = $this->generador->esCorrecta($pregunta, $respuestaElegida);

        $respuesta = Respuesta::query()->create([
            'alumno_id' => $alumno->id,
            'factor_a' => $pregunta['a'],
            'factor_b' => $pregunta['b'],
            'respuesta_elegida' => $respuestaElegida,
            'es_correcta' => $esCorrecta,
        ]);

        $respuestaCorrecta = $this->generador->resultado($pregunta);

        // ❌ Se equivocó: no suma. Se marca el error, la docente lo ve en su
        // reporte y el alumno sigue enseguida con una pregunta de OTRA tabla.
        if (! $esCorrecta) {
            broadcast(new ResultadoRespuesta(
                $alumno->id, false, $respuestaElegida, $respuestaCorrecta, $alumno->aciertos,
            ));
            broadcast(new ErrorRegistrado(
                ['id' => $alumno->id, 'nombre' => $alumno->nombre, 'avatar' => $alumno->avatar],
                $respuesta->datosDelError(),
            ));
            $this->asignarNuevaPregunta($alumno, $partida, cambiarDeTabla: true);

            return self::RESPUESTA_PROCESADA;
        }

        // ✅ Acertó: suma para él y para el aula.
        $misionCumplida = $this->sumarAcierto($alumno, $partida);

        broadcast(new ResultadoRespuesta($alumno->id, true, $respuestaElegida, $respuestaCorrecta, $alumno->aciertos));

        if ($misionCumplida) {
            Alumno::query()->update(['pregunta' => null]);
            $partida->refresh();
            $this->publicarTablero($partida);
            broadcast(new MisionCumplida($partida->total_correctas, $partida->meta));
        } else {
            $this->asignarNuevaPregunta($alumno, $partida);
            $this->publicarTablero($partida->refresh());
        }

        return self::RESPUESTA_PROCESADA;
    }

    /**
     * INICIAR_JUEGO: arranca una ronda nueva con las tablas y meta elegidas.
     *
     * @param  array<int, int>  $tablas
     */
    public function iniciar(array $tablas, int $meta): void
    {
        $partida = Partida::actual();
        $partida->update([
            'estado' => Partida::JUGANDO,
            'tablas' => array_values($tablas),
            'meta' => $meta,
            'total_correctas' => 0,
        ]);

        Alumno::query()->update(['aciertos' => 0, 'pregunta' => null]);

        broadcast(new JuegoIniciado($partida->tablas, $partida->meta));

        // Cada alumno recibe SU pregunta por SU canal privado.
        Alumno::query()->each(fn (Alumno $alumno) => $this->asignarNuevaPregunta($alumno, $partida));

        $this->publicarTablero($partida);
    }

    /**
     * REINICIAR_JUEGO: vuelve a la sala de espera con los contadores en cero.
     * Con $vaciarPista = true además borra a los alumnos (para una clase nueva).
     */
    public function reiniciar(bool $vaciarPista): void
    {
        if ($vaciarPista) {
            Alumno::query()->delete();
        } else {
            Alumno::query()->update(['aciertos' => 0, 'pregunta' => null]);
        }

        $partida = Partida::actual();
        $partida->update(['estado' => Partida::ESPERA, 'total_correctas' => 0]);

        broadcast(new JuegoReiniciado($vaciarPista));
        $this->publicarTablero($partida);
    }

    /**
     * Genera, guarda y envía (NUEVA_PREGUNTA) la próxima operación del alumno.
     * Con $cambiarDeTabla = true la pregunta sale de otra tabla (después de un error).
     *
     * @return array{a: int, b: int, opciones: array<int, int>}
     */
    private function asignarNuevaPregunta(Alumno $alumno, Partida $partida, bool $cambiarDeTabla = false): array
    {
        $pregunta = $this->generador->generar($partida->tablas, $alumno->pregunta, $cambiarDeTabla);

        $alumno->update(['pregunta' => $pregunta]);

        broadcast(new NuevaPregunta($alumno->id, $pregunta));

        return $pregunta;
    }

    /**
     * Suma el acierto y averigua si con él se cumplió la misión.
     *
     * Si dos chicos responden bien al mismo tiempo justo en la meta, los dos
     * pedidos llegan casi juntos. Para que MISION_CUMPLIDA se publique UNA
     * sola vez, el cambio a "terminada" se hace con un UPDATE condicional:
     * solo el primer pedido encuentra la partida todavía en "jugando".
     */
    private function sumarAcierto(Alumno $alumno, Partida $partida): bool
    {
        return DB::transaction(function () use ($alumno, $partida): bool {
            $alumno->increment('aciertos');
            Partida::query()->whereKey($partida->id)->increment('total_correctas');

            $filasCambiadas = Partida::query()
                ->whereKey($partida->id)
                ->where('estado', Partida::JUGANDO)
                ->whereColumn('total_correctas', '>=', 'meta')
                ->update(['estado' => Partida::TERMINADA]);

            return $filasCambiadas === 1;
        });
    }

    /**
     * ACTUALIZAR_TABLERO: manda la foto completa de la carrera.
     */
    private function publicarTablero(Partida $partida): void
    {
        broadcast(new TableroActualizado($partida->resumenParaTablero()));
    }
}
