<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * NUEVA_PREGUNTA: el servidor le manda a UN alumno su próxima operación.
 *
 * Dirección: servidor → tablet. Canal PRIVADO "alumno.{id}": solo la tablet
 * que tiene el token de ese alumno puede suscribirse (ver routes/channels.php).
 *
 * Se mandan las 4 opciones pero NO se dice cuál es la correcta.
 */
class NuevaPregunta implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{a: int, b: int, opciones: array<int, int>}  $pregunta
     */
    public function __construct(public int $alumnoId, public array $pregunta) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("alumno.{$this->alumnoId}")];
    }

    public function broadcastAs(): string
    {
        return 'NUEVA_PREGUNTA';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['pregunta' => $this->pregunta];
    }
}
