<?php

namespace Tests\Unit;

use App\Services\GeneradorPreguntas;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del generador de preguntas (lógica pura, sin base de datos).
 */
class GeneradorPreguntasTest extends TestCase
{
    public function test_genera_preguntas_solo_de_las_tablas_elegidas(): void
    {
        $generador = new GeneradorPreguntas;

        for ($i = 0; $i < 200; $i++) {
            $pregunta = $generador->generar([3, 7]);

            $this->assertContains($pregunta['a'], [3, 7]);
            $this->assertGreaterThanOrEqual(1, $pregunta['b']);
            $this->assertLessThanOrEqual(10, $pregunta['b']);
        }
    }

    public function test_siempre_hay_4_opciones_distintas_y_una_es_la_correcta(): void
    {
        $generador = new GeneradorPreguntas;

        // Probamos TODAS las combinaciones posibles, incluso los casos chicos como 2 × 1.
        foreach (range(2, 9) as $a) {
            foreach (range(1, 10) as $b) {
                $opciones = [$a * $b, ...$generador->opcionesIncorrectas($a, $b)];

                $this->assertCount(4, array_unique($opciones), "Fallo en {$a} × {$b}");
                $this->assertGreaterThan(0, min($opciones));
            }
        }
    }

    public function test_no_repite_la_misma_pregunta_dos_veces_seguidas(): void
    {
        $generador = new GeneradorPreguntas;
        $anterior = ['a' => 2, 'b' => 5];

        for ($i = 0; $i < 100; $i++) {
            $nueva = $generador->generar([2], $anterior);
            $this->assertNotSame([$anterior['a'], $anterior['b']], [$nueva['a'], $nueva['b']]);
            $anterior = $nueva;
        }
    }

    public function test_valida_la_respuesta_y_calcula_el_resultado(): void
    {
        $generador = new GeneradorPreguntas;
        $pregunta = ['a' => 3, 'b' => 4];

        $this->assertTrue($generador->esCorrecta($pregunta, 12));
        $this->assertFalse($generador->esCorrecta($pregunta, 7));
        $this->assertSame(12, $generador->resultado($pregunta));
    }

    public function test_despues_de_un_error_cambia_de_tabla(): void
    {
        $generador = new GeneradorPreguntas;
        $anterior = ['a' => 3, 'b' => 4];

        for ($i = 0; $i < 100; $i++) {
            $nueva = $generador->generar([3, 5, 8], $anterior, cambiarDeTabla: true);

            $this->assertNotSame(3, $nueva['a']);
            $this->assertContains($nueva['a'], [5, 8]);
        }
    }

    public function test_con_una_sola_tabla_cambia_de_operacion(): void
    {
        $generador = new GeneradorPreguntas;
        $anterior = ['a' => 6, 'b' => 2];

        for ($i = 0; $i < 100; $i++) {
            $nueva = $generador->generar([6], $anterior, cambiarDeTabla: true);

            $this->assertSame(6, $nueva['a']);
            $this->assertNotSame(2, $nueva['b']);
        }
    }
}
