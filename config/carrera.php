<?php

/**
 * Configuración de "La Carrera de Multiplicar".
 *
 * Se lee en el código con config('carrera.clave'), por ejemplo config('carrera.meta').
 */
return [

    /*
     * Avatares que puede elegir cada chico (emojis grandes).
     */
    'avatares' => ['🚗', '🏎️', '🚙', '🚌', '🚓', '🚒', '🚀', '🛸', '🐶', '🐱', '🐰', '🦊', '🐼', '🦁', '🐸', '🐢', '🦄', '🐧'],

    /*
     * Tablas que se practican si la docente no elige otras.
     */
    'tablas_por_defecto' => [2, 3, 4, 5, 6, 7, 8, 9],

    /*
     * Meta por defecto de la "Misión del Aula" (correctas entre todos).
     */
    'meta_por_defecto' => 50,

    /*
     * Cantidad de casilleros de la pista en el proyector. Con cada acierto
     * el avatar avanza un casillero; al llegar al final espera en la meta 🏁.
     */
    'casilleros_pista' => 20,

    /*
     * URL que se muestra en el proyector (y en el QR) para que entren las tablets.
     * Si se deja vacía, se detecta sola la IP de la computadora en la red local.
     * Ejemplo para forzarla: AULA_URL=http://192.168.0.15:8000/alumno
     */
    'url_aula' => env('AULA_URL'),

    /*
     * Clave de la docente para usar los controles desde OTRA computadora o
     * por internet (túnel o Laravel Cloud). Si se deja vacía, los controles
     * solo funcionan entrando a http://localhost:8000/tablero desde la misma
     * computadora que corre el servidor (como en el aula).
     */
    'clave_docente' => env('DOCENTE_CLAVE'),

    /*
     * Dónde se conecta el NAVEGADOR al WebSocket.
     *
     * Ojo: no es lo mismo que REVERB_HOST. REVERB_HOST es la dirección que usa
     * LARAVEL para publicar eventos en Reverb (en tu PC, "localhost"). El navegador
     * de un chico en su casa no puede usar "localhost": necesita la dirección
     * pública del túnel. Si estos valores quedan vacíos, se calculan solos
     * (ver App\Services\ConexionReverb).
     *
     * Ejemplo con Cloudflare Tunnel:
     *   REVERB_PUBLIC_HOST=palabras-al-azar.trycloudflare.com
     *   REVERB_PUBLIC_PORT=443
     *   REVERB_PUBLIC_SCHEME=https
     */
    'reverb_publico' => [
        'host' => env('REVERB_PUBLIC_HOST'),
        'port' => env('REVERB_PUBLIC_PORT'),
        'scheme' => env('REVERB_PUBLIC_SCHEME'),
    ],

];
