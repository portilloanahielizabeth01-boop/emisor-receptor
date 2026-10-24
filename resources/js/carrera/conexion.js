/**
 * conexion.js — Todo lo relacionado con hablar con el servidor.
 *
 * Hay DOS caminos de comunicación, y conviene tenerlos bien claros:
 *
 *   1) HTTP (fetch): el navegador PIDE algo y el servidor contesta.
 *      Lo usamos para ENVIAR acciones: unirse, responder, iniciar...
 *
 *   2) WebSocket (Laravel Echo + Reverb): una conexión que queda ABIERTA,
 *      por la que el servidor puede EMPUJAR mensajes cuando quiera,
 *      sin que el navegador pregunte. Lo usamos para RECIBIR eventos.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Reverb habla el mismo "idioma" (protocolo) que Pusher, por eso Echo usa
// la librería pusher-js por debajo.
window.Pusher = Pusher;

/**
 * Crea la conexión WebSocket con Reverb.
 *
 * @param {Object} headersExtra Headers que se mandan al autorizar canales
 *                              privados/presencia (ej: el token del alumno).
 */
export function crearEcho(headersExtra = {}) {
    // window.REVERB lo escribe el servidor en la página (layouts/carrera.blade.php):
    // { key, host, port, scheme }. Ver App\Services\ConexionReverb.
    const reverb = window.REVERB;

    return new Echo({
        broadcaster: 'reverb',
        key: reverb.key,

        // En el aula, host viene vacío y usamos el mismo host con el que se abrió
        // la página: si la tablet entró por http://192.168.0.15:8000, el WebSocket
        // va a 192.168.0.15:8080. Por internet (túnel o Laravel Cloud), el servidor
        // nos da la dirección pública de Reverb, que es otra distinta de la de la app.
        wsHost: reverb.host ?? window.location.hostname,
        wsPort: reverb.port,
        wssPort: reverb.port,
        // https → WebSocket cifrado (wss://). Una página https NO puede abrir un
        // ws:// sin cifrar: el navegador lo bloquea.
        forceTLS: reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],

        // Echo pide permiso a /broadcasting/auth antes de entrar a un canal
        // privado o de presencia; estos headers viajan en ese pedido.
        auth: { headers: { ...headersExtra } },
    });
}

/**
 * Avisa cada vez que cambia el estado de la conexión WebSocket.
 * Estados posibles: "connecting", "connected", "unavailable", "disconnected"...
 * pusher-js reintenta conectarse SOLO cuando se corta.
 *
 * @param {Echo} echo
 * @param {(actual: string, anterior: string) => void} callback
 */
export function alCambiarConexion(echo, callback) {
    echo.connector.pusher.connection.bind('state_change', ({ previous, current }) => {
        callback(current, previous);
    });
}

/**
 * Hace un pedido HTTP al servidor y devuelve { status, datos }.
 * Agrega el token CSRF de Laravel (protección contra formularios falsos).
 *
 * @param {string} url
 * @param {{ metodo?: string, datos?: Object, headers?: Object }} opciones
 */
export async function pedir(url, { metodo = 'GET', datos = null, headers = {} } = {}) {
    const respuesta = await fetch(url, {
        method: metodo,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            ...headers,
        },
        body: datos ? JSON.stringify(datos) : null,
    });

    let cuerpo = null;
    try {
        cuerpo = await respuesta.json();
    } catch {
        // Algunas respuestas no traen JSON; no pasa nada.
    }

    return { status: respuesta.status, datos: cuerpo };
}
