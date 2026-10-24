<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REINICIAR_JUEGO: la docente reinició la ronda.
 *
 * Dirección: servidor → todos. Canal PÚBLICO "carrera".
 * Las tablets vuelven a la sala de espera. Si además se "vació la pista"
 * (nueva clase), los alumnos fueron borrados y las tablets vuelven a
 * la pantalla de nombre y avatar.
 */
class JuegoReiniciado implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public bool $pistaVaciada) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('carrera')];
    }

    public function broadcastAs(): string
    {
        return 'REINICIAR_JUEGO';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['pista_vaciada' => $this->pistaVaciada];
    }
}
