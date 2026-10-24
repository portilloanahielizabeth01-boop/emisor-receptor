<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ingreso de la docente con clave.
 *
 * En el aula no hace falta: alcanza con abrir el tablero en localhost.
 * Por internet sí, porque el servidor ya no puede saber "quién es la docente"
 * mirando de qué computadora viene el pedido.
 */
class DocenteController extends Controller
{
    /**
     * POST /docente/entrar
     */
    public function entrar(Request $request): RedirectResponse
    {
        $request->validate([
            'clave' => ['required', 'string'],
        ]);

        $claveConfigurada = (string) config('carrera.clave_docente');

        // hash_equals compara en tiempo constante: no deja adivinar la clave
        // midiendo cuánto tarda la respuesta. Si no hay clave configurada,
        // no se puede entrar por este camino.
        if ($claveConfigurada === '' || ! hash_equals($claveConfigurada, $request->string('clave')->toString())) {
            return back()->withErrors(['clave' => 'Clave incorrecta.']);
        }

        // Nuevo id de sesión al cambiar de permisos (evita "fijación de sesión").
        $request->session()->regenerate();
        $request->session()->put('es_docente', true);

        return redirect()->route('tablero');
    }

    /**
     * POST /docente/salir
     */
    public function salir(Request $request): RedirectResponse
    {
        $request->session()->forget('es_docente');
        $request->session()->regenerate();

        return redirect()->route('tablero');
    }
}
