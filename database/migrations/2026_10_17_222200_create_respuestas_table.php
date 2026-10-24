<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "respuestas": historial de cada intento de cada alumno.
 *
 * No se usa para jugar, pero permite que la docente vea después
 * qué tablas le cuestan más a cada chico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas', function (Blueprint $table) {
            $table->id();
            // Si se borra el alumno, se borran sus respuestas (cascade).
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->unsignedTinyInteger('factor_a');
            $table->unsignedTinyInteger('factor_b');
            $table->unsignedSmallInteger('respuesta_elegida');
            $table->boolean('es_correcta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas');
    }
};
