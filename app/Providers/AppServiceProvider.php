<?php

namespace App\Providers;

use App\Models\Alumno;
use App\Services\ConexionReverb;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registrarGuardsDelJuego();
        $this->compartirConexionReverbConLasVistas();
    }

    /**
     * Define CÓMO reconoce Laravel a cada tipo de cliente (los guards se
     * declaran en config/auth.php).
     *
     * - "alumno": la tablet manda su token secreto en el header X-Alumno-Token.
     *   Se usa un header y no la cookie de sesión porque las cookies se comparten
     *   entre pestañas: así cada pestaña puede ser un alumno distinto.
     *
     * - "docente": se la reconoce de dos maneras:
     *   a) Entró con la clave (DOCENTE_CLAVE) → queda marcada en su sesión.
     *      Sirve desde cualquier lugar: otra compu, el túnel o Laravel Cloud.
     *   b) Abrió http://localhost:8000 en la MISMA computadora del servidor,
     *      sin pasar por ningún proxy (el modo "aula" de siempre, sin clave).
     */
    private function registrarGuardsDelJuego(): void
    {
        Auth::viaRequest('token-alumno', function (Request $request): ?Alumno {
            $token = $request->header('X-Alumno-Token');

            if (! is_string($token) || $token === '') {
                return null;
            }

            return Alumno::query()->where('token', $token)->first();
        });

        Auth::viaRequest('docente-local', function (Request $request): ?GenericUser {
            $entroConClave = $request->hasSession() && $request->session()->get('es_docente') === true;

            if (! $entroConClave && ! $this->esPedidoDirectoDesdeEstaComputadora($request)) {
                return null;
            }

            // La docente no está en la base de datos: es un usuario "genérico".
            return new GenericUser(['id' => 'docente', 'nombre' => 'Docente']);
        });
    }

    /**
     * ¿El pedido lo hizo un navegador de ESTA computadora, sin intermediarios?
     *
     * Cuidado con el túnel: cloudflared corre en esta misma PC, así que para
     * Laravel TODOS los pedidos que llegan por internet vienen de 127.0.0.1.
     * Si solo miráramos la IP, cualquier chico desde su casa sería "docente".
     * La diferencia es que lo que pasa por Cloudflare trae siempre los headers
     * CF-Connecting-IP y X-Forwarded-For (la IP real del visitante), y quien
     * entra por internet no puede sacarlos porque los agrega Cloudflare.
     *
     * Se usa REMOTE_ADDR (quién abrió la conexión) y no $request->ip(), porque
     * ip() puede cambiar según en qué proxies se confíe (config/trustedproxy.php).
     */
    private function esPedidoDirectoDesdeEstaComputadora(Request $request): bool
    {
        $vinoPorUnProxy = $request->headers->has('CF-Connecting-IP')
            || $request->headers->has('X-Forwarded-For');

        return ! $vinoPorUnProxy
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
    }

    /**
     * Le pasa a la plantilla común (layouts/carrera) la dirección del WebSocket
     * que tiene que usar el navegador.
     */
    private function compartirConexionReverbConLasVistas(): void
    {
        View::composer('layouts.carrera', function (ViewContract $vista): void {
            $vista->with('reverbNavegador', app(ConexionReverb::class)->paraNavegador());
        });
    }
}
