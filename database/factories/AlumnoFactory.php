<?php

namespace Database\Factories;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fábrica de alumnos de prueba (se usa en los tests).
 *
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(40),
            'nombre' => fake()->firstName(),
            'avatar' => fake()->randomElement(config('carrera.avatares')),
            'aciertos' => 0,
            'pregunta' => null,
        ];
    }

    /**
     * Alumno que ya tiene una pregunta en pantalla (por defecto 4 × 5).
     *
     * @param  array<int, int>  $opciones
     */
    public function conPregunta(int $a = 4, int $b = 5, array $opciones = [20, 16, 24, 25]): static
    {
        return $this->state(fn () => [
            'pregunta' => ['a' => $a, 'b' => $b, 'opciones' => $opciones],
        ]);
    }
}
