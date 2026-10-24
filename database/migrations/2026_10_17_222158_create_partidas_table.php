<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "partidas": guarda el estado general del juego del aula.
 *
 * En la práctica siempre hay UNA sola fila (la partida actual), pero
 * tenerla en la base de datos permite que el estado sobreviva aunque
 * se reinicie el servidor o se recargue el proyector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas', function (Blueprint $table) {
            $table->id();
            // "espera" (sala de espera), "jugando" o "terminada" (misión cumplida).
            $table->string('estado')->default('espera');
            // Tablas que se practican, guardadas como JSON. Ej: [2,3,4,5].
            $table->json('tablas');
            // Meta de la "Misión del Aula": respuestas correctas entre todos.
            $table->unsignedInteger('meta')->default(50);
            // Contador cooperativo: correctas de todo el curso en esta ronda.
            $table->unsignedInteger('total_correctas')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas');
    }
};
