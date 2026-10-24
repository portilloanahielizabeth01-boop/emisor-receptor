<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * INICIAR_JUEGO: la docente tocó "Iniciar".
 *
 * Dirección: servidor → todos. Canal PÚBLICO "carrera".
 * Las tablets salen de la sala de espera (cada una recibe además
 * su propia NUEVA_PREGUNTA por su canal privado).
 */
class JuegoIniciado implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $tablas
     */
    public function __construct(public array $tablas, public int $meta) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('carrera')];
    }

    public function broadcastAs(): string
    {
        return 'INICIAR_JUEGO';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['tablas' => $this->tablas, 'meta' => $this->meta];
    }
}
