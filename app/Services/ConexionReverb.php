<?php

namespace App\Services;

/**
 * Calcula a dónde tiene que conectarse el NAVEGADOR para el WebSocket.
 *
 * Antes esto se fijaba al compilar con Vite (variables VITE_REVERB_*), así que
 * cambiar de dirección obligaba a correr "npm run build" otra vez. Ahora lo
 * calcula el servidor en cada pedido y lo escribe en la página: alcanza con
 * cambiar el .env. Eso importa con Cloudflare Tunnel, porque la dirección
 * gratuita cambia cada vez que se abre el túnel.
 *
 * Los tres escenarios:
 *
 *  | Escenario          | host                               | port | scheme |
 *  |--------------------|------------------------------------|------|--------|
 *  | Red del aula       | null → el JS usa la IP de la página| 8080 | http   |
 *  | Cloudflare Tunnel  | REVERB_PUBLIC_HOST (*.trycloudflare)| 443 | https  |
 *  | Laravel Cloud      | REVERB_HOST (ws-…-reverb.laravel.cloud)| 443 | https |
 */
class ConexionReverb
{
    /**
     * Direcciones que solo sirven dentro de la propia computadora.
     */
    private const HOSTS_LOCALES = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];

    /**
     * Datos que se le pasan a Laravel Echo en el navegador. Nunca incluye el
     * "secret" de Reverb: ese solo lo usa el servidor para publicar.
     *
     * @return array{key: string|null, host: string|null, port: int, scheme: string}
     */
    public function paraNavegador(): array
    {
        $servidor = config('broadcasting.connections.reverb');
        $publico = config('carrera.reverb_publico');

        return [
            'key' => $servidor['key'] ?? null,
            'host' => $this->host($publico['host'] ?? null, $servidor['options']['host'] ?? null),
            'port' => (int) ($publico['port'] ?: ($servidor['options']['port'] ?? 8080)),
            'scheme' => $publico['scheme'] ?: ($servidor['options']['scheme'] ?? 'http'),
        ];
    }

    /**
     * 1) Si se indicó una dirección pública (túnel), se usa esa.
     * 2) Si no, y Laravel publica en un Reverb que NO está en esta máquina
     *    (Laravel Cloud), el navegador va al mismo lugar.
     * 3) Si no (aula), null: el navegador usa la dirección con la que abrió la página.
     */
    private function host(?string $hostPublico, ?string $hostDelServidor): ?string
    {
        if (filled($hostPublico)) {
            return $hostPublico;
        }

        if (filled($hostDelServidor) && ! in_array($hostDelServidor, self::HOSTS_LOCALES, true)) {
            return $hostDelServidor;
        }

        return null;
    }
}
