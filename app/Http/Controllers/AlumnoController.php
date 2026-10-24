<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Partida;
use App\Services\Carrera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pedidos que llegan desde la tablet del alumno.
 *
 * Todas las respuestas son JSON: el resultado "importante" (si acertó,
 * la nueva pregunta, etc.) NO vuelve en la respuesta HTTP, sino que
 * llega por WebSocket como evento. Así el mismo mecanismo sirve para
 * el alumno, el proyector y las demás tablets.
 */
class AlumnoController extends Controller
{
    public function __construct(private Carrera $carrera) {}

    /**
     * GET /alumno → la página de la tablet.
     */
    public function pantalla(): View
    {
        return view('alumno', [
            'avatares' => config('carrera.avatares'),
        ]);
    }

    /**
     * POST /alumno/unirse → evento UNIRSE.
     * Devuelve el token secreto que la tablet guardará para identificarse.
     */
    public function unirse(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:20'],
            'avatar' => ['required', 'string', Rule::in(config('carrera.avatares'))],
        ]);

        $alumno = $this->carrera->unirAlumno(trim($datos['nombre']), $datos['avatar']);

        return response()->json([
            'token' => $alumno->token,
            'alumno' => $alumno->datosPublicos(),
        ], 201);
    }

    /**
     * GET /alumno/estado → "¿dónde me había quedado?".
     * La tablet lo pide al cargar la página y cada vez que se reconecta.
     */
    public function estado(Request $request): JsonResponse
    {
        /** @var Alumno $alumno */
        $alumno = $request->user('alumno');
        $partida = Partida::actual();

        return response()->json([
            'alumno' => $alumno->datosPublicos(),
            'partida' => [
                'estado' => $partida->estado,
                'meta' => $partida->meta,
                'total_correctas' => $partida->total_correctas,
            ],
            'pregunta' => $this->carrera->preguntaActualDe($alumno, $partida),
        ]);
    }

    /**
     * POST /alumno/responder → evento ENVIAR_RESPUESTA.
     * Responde 202 ("recibido"): el resultado viaja por WebSocket.
     */
    public function responder(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'a' => ['required', 'integer'],
            'b' => ['required', 'integer'],
            'respuesta' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        /** @var Alumno $alumno */
        $alumno = $request->user('alumno');

        $resultado = $this->carrera->responder(
            $alumno, (int) $datos['a'], (int) $datos['b'], (int) $datos['respuesta'],
        );

        return response()->json(
            ['resultado' => $resultado],
            $resultado === Carrera::RESPUESTA_PROCESADA ? 202 : 409,
        );
    }
}
