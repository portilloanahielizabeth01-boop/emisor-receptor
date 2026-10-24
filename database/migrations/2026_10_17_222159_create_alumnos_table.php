<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "alumnos": cada chico que se unió desde una tablet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            // Token secreto que identifica a la tablet (ver Alumno::crearConToken).
            $table->string('token', 64)->unique();
            $table->string('nombre', 20);
            $table->string('avatar', 16);
            // Respuestas correctas en la ronda actual (= cuánto avanzó en la pista).
            $table->unsignedInteger('aciertos')->default(0);
            // Pregunta que el alumno tiene en pantalla AHORA: {"a":4,"b":5,"opciones":[...]}.
            // Se guarda en el servidor para validar la respuesta sin confiar en el cliente.
            $table->json('pregunta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
