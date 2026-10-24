<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado general del juego del aula (siempre se usa una sola fila).
 *
 * @property string $estado "espera" | "jugando" | "terminada"
 * @property array<int, int> $tablas
 * @property int $meta
 * @property int $total_correctas
 */
#[Fillable(['estado', 'tablas', 'meta', 'total_correctas'])]
class Partida extends Model
{
    public const ESPERA = 'espera';

    public const JUGANDO = 'jugando';

    public const TERMINADA = 'terminada';

    /**
     * Devuelve la partida actual. Si todavía no existe ninguna, la crea
     * con la configuración por defecto (config/carrera.php).
     */
    public static function actual(): self
    {
        return self::query()->firstOrCreate([], [
            'estado' => self::ESPERA,
            'tablas' => config('carrera.tablas_por_defecto'),
            'meta' => config('carrera.meta_por_defecto'),
            'total_correctas' => 0,
        ]);
    }

    public function estaJugando(): bool
    {
        return $this->estado === self::JUGANDO;
    }

    /**
     * Datos que necesita el proyector para dibujar la pista y la barra de misión.
     * Se usa tanto en el evento ACTUALIZAR_TABLERO como al cargar la página.
     *
     * @return array{estado: string, tablas: array<int, int>, meta: int, total_correctas: int, alumnos: array<int, array<string, mixed>>}
     */
    public function resumenParaTablero(): array
    {
        return [
            'estado' => $this->estado,
            'tablas' => $this->tablas,
            'meta' => $this->meta,
            'total_correctas' => $this->total_correctas,
            'alumnos' => Alumno::query()->orderBy('id')->get()
                ->map(fn (Alumno $alumno) => $alumno->datosPublicos())
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tablas' => 'array',
            'meta' => 'integer',
            'total_correctas' => 'integer',
        ];
    }
}
