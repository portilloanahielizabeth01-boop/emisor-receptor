<?php

namespace App\Models;

use Database\Factories\AlumnoFactory;
use Illuminate\Auth\Authenticatable as PuedeAutenticarse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un chico conectado desde una tablet.
 *
 * Implementa Authenticatable para que Laravel lo pueda tratar como "usuario"
 * al autorizar los canales privados y de presencia (routes/channels.php).
 * No tiene contraseña: se identifica con un token secreto que guarda la tablet.
 *
 * @property string $token
 * @property string $nombre
 * @property string $avatar
 * @property int $aciertos
 * @property array{a: int, b: int, opciones: array<int, int>}|null $pregunta
 */
#[Fillable(['token', 'nombre', 'avatar', 'aciertos', 'pregunta'])]
#[Hidden(['token'])]
class Alumno extends Model implements Authenticatable
{
    /** @use HasFactory<AlumnoFactory> */
    use HasFactory, PuedeAutenticarse;

    /**
     * Crea un alumno nuevo con un token aleatorio de 40 caracteres.
     * El token es la "llave" de la tablet: sin él no se puede responder
     * en nombre de ese alumno ni escuchar su canal privado.
     */
    public static function crearConToken(string $nombre, string $avatar): self
    {
        return self::query()->create([
            'token' => Str::random(40),
            'nombre' => $nombre,
            'avatar' => $avatar,
            'aciertos' => 0,
        ]);
    }

    /**
     * @return HasMany<Respuesta, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(Respuesta::class);
    }

    /**
     * Datos que pueden ver todos (el proyector). Nunca incluye el token
     * ni la pregunta actual.
     *
     * @return array{id: int, nombre: string, avatar: string, aciertos: int}
     */
    public function datosPublicos(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'avatar' => $this->avatar,
            'aciertos' => $this->aciertos,
        ];
    }

    /**
     * Los alumnos no usan "recordarme", así que desactivamos esa columna.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aciertos' => 'integer',
            'pregunta' => 'array',
        ];
    }
}
