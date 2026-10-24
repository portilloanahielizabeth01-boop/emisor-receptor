<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * MISION_CUMPLIDA: entre todos llegaron a la meta.
 *
 * Dirección: servidor → todos. Canal PÚBLICO "carrera".
 * Proyector y tablets tiran confetti al mismo tiempo.
 */
class MisionCumplida implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $totalCorrectas, public int $meta) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('carrera')];
    }

    public function broadcastAs(): string
    {
        return 'MISION_CUMPLIDA';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['total_correctas' => $this->totalCorrectas, 'meta' => $this->meta];
    }
}
