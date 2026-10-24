<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RESULTADO_RESPUESTA: el servidor le dice al alumno si acertó.
 *
 * Dirección: servidor → tablet. Canal PRIVADO "alumno.{id}".
 * Incluye la respuesta correcta para que, si se equivocó, la tablet marque
 * el error. La pregunta siguiente llega aparte (NUEVA_PREGUNTA).
 */
class ResultadoRespuesta implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public int $alumnoId,
        public bool $esCorrecta,
        public int $respuestaElegida,
        public int $respuestaCorrecta,
        public int $aciertos,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("alumno.{$this->alumnoId}")];
    }

    public function broadcastAs(): string
    {
        return 'RESULTADO_RESPUESTA';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'es_correcta' => $this->esCorrecta,
            'respuesta_elegida' => $this->respuestaElegida,
            'respuesta_correcta' => $this->respuestaCorrecta,
            'aciertos' => $this->aciertos,
        ];
    }
}
