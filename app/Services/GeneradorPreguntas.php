<?php

namespace App\Services;

/**
 * Arma las preguntas de multiplicar y sus opciones.
 *
 * Toda la lógica "matemática" del juego vive acá, en el SERVIDOR.
 * El navegador nunca decide si una respuesta es correcta.
 */
class GeneradorPreguntas
{
    /**
     * Genera una pregunta del tipo "a × b" donde "a" es una de las tablas
     * elegidas por la docente y "b" va del 1 al 10.
     *
     * Con $cambiarDeTabla = true (después de un error) la pregunta nueva sale
     * de OTRA tabla, para que la dinámica siga sin trabarse en la misma.
     * Si la docente eligió una sola tabla, no hay otra: se cambia solo "b".
     *
     * @param  array<int, int>  $tablas  Ej: [2, 3, 4]
     * @param  array{a: int, b: int}|null  $anterior  Pregunta anterior, para no repetirla seguida.
     * @return array{a: int, b: int, opciones: array<int, int>}
     */
    public function generar(array $tablas, ?array $anterior = null, bool $cambiarDeTabla = false): array
    {
        if ($cambiarDeTabla && $anterior !== null) {
            $otrasTablas = array_values(array_diff($tablas, [$anterior['a']]));
            $tablas = $otrasTablas ?: $tablas;
        }

        do {
            $a = $tablas[array_rand($tablas)];
            $b = random_int(1, 10);
            $esIgualALaAnterior = $anterior !== null && $anterior['a'] === $a && $anterior['b'] === $b;
        } while ($esIgualALaAnterior);

        // Las 4 opciones: la correcta + 3 incorrectas, mezcladas al azar.
        $opciones = [$a * $b, ...$this->opcionesIncorrectas($a, $b)];
        shuffle($opciones);

        return ['a' => $a, 'b' => $b, 'opciones' => $opciones];
    }

    /**
     * Devuelve 3 respuestas incorrectas pero "creíbles": son los errores
     * típicos de los chicos (confundirse por una fila de la tabla, sumar
     * en vez de multiplicar, errarle por uno, etc.).
     *
     * @return array<int, int>
     */
    public function opcionesIncorrectas(int $a, int $b): array
    {
        $correcta = $a * $b;

        $candidatas = [
            $a * ($b + 1),   // se pasó una fila de la tabla
            $a * ($b - 1),   // le faltó una fila
            ($a + 1) * $b,   // usó la tabla siguiente
            ($a - 1) * $b,   // usó la tabla anterior
            $correcta + 1,
            $correcta - 1,
            $correcta + 2,
            $a + $b,         // sumó en vez de multiplicar
            $correcta + 10,
        ];

        // Sacamos las que no sirven: cero/negativas, iguales a la correcta o repetidas.
        $validas = array_values(array_unique(array_filter(
            $candidatas,
            fn (int $numero) => $numero > 0 && $numero !== $correcta,
        )));

        shuffle($validas);

        return array_slice($validas, 0, 3);
    }

    /**
     * Compara la opción elegida con el resultado real, calculado acá.
     *
     * @param  array{a: int, b: int}  $pregunta
     */
    public function esCorrecta(array $pregunta, int $respuestaElegida): bool
    {
        return $respuestaElegida === $this->resultado($pregunta);
    }

    /**
     * El resultado correcto de la pregunta, calculado acá.
     *
     * @param  array{a: int, b: int}  $pregunta
     */
    public function resultado(array $pregunta): int
    {
        return $pregunta['a'] * $pregunta['b'];
    }
}
