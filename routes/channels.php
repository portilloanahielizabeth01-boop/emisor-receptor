<?php

use App\Models\Alumno;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Autorización de canales
|--------------------------------------------------------------------------
|
| Los canales PÚBLICOS (como "carrera") no necesitan nada: cualquiera escucha.
|
| Para los canales PRIVADOS y de PRESENCIA, cuando el navegador quiere
| suscribirse, Laravel Echo pregunta primero a /broadcasting/auth. Laravel
| busca al usuario con los guards indicados y ejecuta la función de abajo:
| si devuelve true (o un array), Reverb deja pasar la suscripción.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Canal PRIVADO de cada alumno: "alumno.5" solo lo puede escuchar el alumno 5.
 * Así nadie puede espiar las preguntas o resultados de otro chico.
 */
Broadcast::channel('alumno.{alumnoId}', function ($usuario, int $alumnoId): bool {
    return $usuario instanceof Alumno && $usuario->id === $alumnoId;
}, ['guards' => ['alumno']]);

/*
 * Canal PRIVADO de la docente: por acá viaja el reporte de errores.
 * Solo entra la computadora del servidor (guard "docente"); las tablets no.
 */
Broadcast::channel('docente', function ($usuario): bool {
    return $usuario instanceof GenericUser;
}, ['guards' => ['docente']]);

/*
 * Canal de PRESENCIA "aula": Reverb lleva la lista de quién está conectado
 * y avisa cuando alguien entra (joining) o se va (leaving).
 * Lo que devolvemos acá es lo que los demás ven de cada miembro.
 *
 * Entran los alumnos (por token) y la docente (desde localhost), porque el
 * proyector también tiene que estar adentro para recibir los avisos.
 */
Broadcast::channel('aula', function ($usuario): ?array {
    if ($usuario instanceof Alumno) {
        return ['id' => $usuario->id, 'tipo' => 'alumno', 'nombre' => $usuario->nombre];
    }

    if ($usuario instanceof GenericUser) {
        return ['id' => 'docente', 'tipo' => 'docente', 'nombre' => 'Docente'];
    }

    return null;
}, ['guards' => ['alumno', 'docente']]);
