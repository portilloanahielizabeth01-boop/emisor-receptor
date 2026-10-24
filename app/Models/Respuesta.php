<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Un intento de respuesta de un alumno (historial).
 *
 * @property int $alumno_id
 * @property int $factor_a La tabla (en "7 × 3", la tabla del 7).
 * @property int $factor_b
 * @property int $respuesta_elegida
 * @property bool $es_correcta
 */
#[Fillable(['alumno_id', 'factor_a', 'factor_b', 'respuesta_elegida', 'es_correcta'])]
class Respuesta extends Model
{
    /**
     * Cuántos errores de cada alumno se listan en el reporte (los más recientes).
     */
    public const ERRORES_POR_ALUMNO = 20;

    /**
     * Reporte para el seguimiento de la docente: en qué tabla y en qué
     * operación exacta se equivocó cada alumno.
     *
     * Cuenta todos los errores de los alumnos de la clase actual, pero de cada
     * alumno lista solo los últimos ERRORES_POR_ALUMNO, para que la pantalla
     * no se llene. Se vacía con "Nueva clase", porque al borrar a los alumnos
     * se borran sus respuestas.
     *
     * @return array{
     *     total: int,
     *     por_tabla: array<int, array{tabla: int, errores: int}>,
     *     por_alumno: array<int, array{id: int, nombre: string, avatar: string, total_errores: int, errores: array<int, array{a: int, b: int, respuesta_elegida: int, respuesta_correcta: int, hora: string}>}>
     * }
     */
    public static function reporteDeErrores(): array
    {
        $errores = self::query()
            ->where('es_correcta', false)
            ->with('alumno')
            ->latest('id')
            ->get();

        $porTabla = $errores->countBy('factor_a')
            ->map(fn (int $cantidad, int $tabla) => ['tabla' => $tabla, 'errores' => $cantidad])
            ->sortBy([['errores', 'desc'], ['tabla', 'asc']])
            ->values()
            ->all();

        $porAlumno = $errores->groupBy('alumno_id')
            ->map(fn (Collection $erroresDelAlumno) => [
                'id' => $erroresDelAlumno->first()->alumno->id,
                'nombre' => $erroresDelAlumno->first()->alumno->nombre,
                'avatar' => $erroresDelAlumno->first()->alumno->avatar,
                'total_errores' => $erroresDelAlumno->count(),
                'errores' => $erroresDelAlumno->take(self::ERRORES_POR_ALUMNO)
                    ->map(fn (self $error) => $error->datosDelError())
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('total_errores')
            ->values()
            ->all();

        return [
            'total' => $errores->count(),
            'por_tabla' => $porTabla,
            'por_alumno' => $porAlumno,
        ];
    }

    /**
     * @return array{a: int, b: int, respuesta_elegida: int, respuesta_correcta: int, hora: string}
     */
    public function datosDelError(): array
    {
        return [
            'a' => $this->factor_a,
            'b' => $this->factor_b,
            'respuesta_elegida' => $this->respuesta_elegida,
            'respuesta_correcta' => $this->factor_a * $this->factor_b,
            'hora' => $this->created_at->format('H:i'),
        ];
    }

    /**
     * @return BelongsTo<Alumno, $this>
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_a' => 'integer',
            'factor_b' => 'integer',
            'respuesta_elegida' => 'integer',
            'es_correcta' => 'boolean',
        ];
    }
}
