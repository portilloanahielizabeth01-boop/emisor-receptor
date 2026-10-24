<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ACTUALIZAR_TABLERO: foto completa del juego (pista + misión del aula).
 *
 * Dirección: servidor → todos. Canal PÚBLICO "carrera".
 * El proyector redibuja la pista; las tablets actualizan la barra de misión.
 *
 * Se manda el estado COMPLETO (no solo "Juan sumó 1") para que, si a alguien
 * se le perdió un mensaje, con el siguiente quede sincronizado otra vez.
 */
class TableroActualizado implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $resumen  Ver Partida::resumenParaTablero()
     */
    public function __construct(public array $resumen) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('carrera')];
    }

    public function broadcastAs(): string
    {
        return 'ACTUALIZAR_TABLERO';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->resumen;
    }
}
