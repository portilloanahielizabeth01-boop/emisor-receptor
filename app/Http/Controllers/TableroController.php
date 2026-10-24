<?php

namespace App\Http\Controllers;

use App\Models\Partida;
use App\Models\Respuesta;
use App\Services\Carrera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla del proyector y controles de la docente.
 *
 * Los controles (iniciar / reiniciar) están protegidos en routes/web.php
 * con el middleware "auth:docente": solo funcionan desde la computadora
 * donde corre el servidor.
 */
class TableroController extends Controller
{
    public function __construct(private Carrera $carrera) {}

    /**
     * GET /tablero → la página del proyector.
     */
    public function pantalla(Request $request): View
    {
        $esDocente = $request->user('docente') !== null;

        return view('tablero', [
            'resumen' => Partida::actual()->resumenParaTablero(),
            'urlAula' => $this->urlParaTablets($request),
            'esDocente' => $esDocente,
            'hayClaveDocente' => filled(config('carrera.clave_docente')),
            // Los errores son solo para la docente: las tablets no los reciben.
            'reporteErrores' => $esDocente ? Respuesta::reporteDeErrores() : null,
            'casilleros' => config('carrera.casilleros_pista'),
        ]);
    }

    /**
     * GET /tablero/estado → foto actual (se usa al reconectarse).
     */
    public function estado(): JsonResponse
    {
        return response()->json(Partida::actual()->resumenParaTablero());
    }

    /**
     * GET /tablero/errores → reporte de errores para la docente (se usa al reconectarse).
     */
    public function errores(): JsonResponse
    {
        return response()->json(Respuesta::reporteDeErrores());
    }

    /**
     * POST /tablero/iniciar → evento INICIAR_JUEGO.
     */
    public function iniciar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tablas' => ['required', 'array', 'min:1'],
            'tablas.*' => ['integer', 'between:1,10', 'distinct'],
            'meta' => ['required', 'integer', 'between:5,500'],
        ]);

        $tablas = array_map('intval', $datos['tablas']);
        sort($tablas);

        $this->carrera->iniciar($tablas, (int) $datos['meta']);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /tablero/reiniciar → evento REINICIAR_JUEGO.
     */
    public function reiniciar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'vaciar_pista' => ['sometimes', 'boolean'],
        ]);

        $this->carrera->reiniciar((bool) ($datos['vaciar_pista'] ?? false));

        return response()->json(['ok' => true]);
    }

    /**
     * Arma la dirección que tienen que escribir las tablets (y que va en el QR).
     *
     * Orden de prioridad:
     *  1) AULA_URL, si la docente la escribió a mano en el .env.
     *  2) APP_URL, si es una dirección pública (túnel o Laravel Cloud):
     *     los chicos entran desde sus casas por esa dirección.
     *  3) La IP de esta computadora en la red local (modo aula). "localhost"
     *     no sirve: para una tablet, localhost es la propia tablet.
     */
    private function urlParaTablets(Request $request): string
    {
        if (filled(config('carrera.url_aula'))) {
            return config('carrera.url_aula');
        }

        $hostDeAppUrl = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (filled($hostDeAppUrl) && ! in_array($hostDeAppUrl, ['localhost', '127.0.0.1'], true)) {
            return rtrim(config('app.url'), '/').'/alumno';
        }

        $ips = gethostbynamel(gethostname()) ?: [];

        // Preferimos las IPs típicas de redes hogareñas/escolares (192.168.x.x, 10.x.x.x).
        $ipsDeRedLocal = array_filter(
            $ips,
            fn (string $ip) => str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.'),
        );

        $ip = reset($ipsDeRedLocal) ?: ($ips[0] ?? $request->getHost());

        return "http://{$ip}:{$request->getPort()}/alumno";
    }
}
