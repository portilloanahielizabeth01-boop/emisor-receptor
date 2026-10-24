<?php

/**
 * Proxies de confianza.
 *
 * Cuando la app se usa por internet, entre el navegador y Laravel hay un
 * intermediario (proxy): cloudflared en el túnel, o el balanceador de Laravel
 * Cloud. El navegador habla HTTPS con el proxy, pero el proxy le habla a
 * Laravel por HTTP común y le avisa con headers como "X-Forwarded-Proto: https"
 * cómo era el pedido original.
 *
 * Laravel solo cree esos headers si el pedido viene de un proxy de esta lista.
 * Si no confía, piensa que la página es "http://" y arma mal las direcciones
 * del CSS y del JavaScript: el navegador las bloquea por "contenido mixto".
 *
 * Lo lee el middleware Illuminate\Http\Middleware\TrustProxies.
 *
 * Valores posibles de TRUSTED_PROXIES:
 *   (vacío)          → no confiar en ninguno. Es lo correcto en la red del aula.
 *                      En Laravel Cloud, Laravel ya confía automáticamente.
 *   127.0.0.1,::1    → confiar solo en esta misma computadora (cloudflared).
 */
return [

    'proxies' => env('TRUSTED_PROXIES'),

];
