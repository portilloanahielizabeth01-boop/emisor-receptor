<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * UNIRSE: un chico entró al juego desde su tablet.
 *
 * Dirección: servidor → proyector. Canal PÚBLICO "carrera".
 * El proyector lo usa para mostrar "¡Llegó Juan 🚗!".
 *
 * ShouldBroadcastNow (hija de ShouldBroadcast) envía el evento a Reverb
 * en el mismo momento, sin pasar por una cola de trabajos.
 */
class AlumnoUnido implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{id: int, nombre: string, avatar: string, aciertos: int}  $alumno
     */
    public function __construct(public array $alumno) {}

    /**
     * En qué canal(es) se publica el evento.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('carrera')];
    }

    /**
     * Nombre con el que lo escucha JavaScript: .listen('.UNIRSE', ...)
     */
    public function broadcastAs(): string
    {
        return 'UNIRSE';
    }

    /**
     * Datos exactos que viajan por el WebSocket (JSON).
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['alumno' => $this->alumno];
    }
}
