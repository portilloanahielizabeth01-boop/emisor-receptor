<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ERROR_REGISTRADO: un alumno se equivocó; la docente ve en qué tabla y operación.
 *
 * Dirección: servidor → docente. Canal PRIVADO "docente": solo lo puede
 * escuchar la computadora de la docente (ver routes/channels.php), así
 * ninguna tablet ve los errores de sus compañeros.
 *
 * Se manda SOLO el error nuevo (no el reporte entero): Reverb no acepta
 * mensajes de más de 10 KB y el reporte crece con cada error. El reporte
 * completo se pide por HTTP al abrir el tablero y al reconectarse
 * (GET /tablero/errores).
 */
class ErrorRegistrado implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{id: int, nombre: string, avatar: string}  $alumno
     * @param  array{a: int, b: int, respuesta_elegida: int, respuesta_correcta: int, hora: string}  $error
     */
    public function __construct(public array $alumno, public array $error) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('docente')];
    }

    public function broadcastAs(): string
    {
        return 'ERROR_REGISTRADO';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['alumno' => $this->alumno, 'error' => $this->error];
    }
}
