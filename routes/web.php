<?php

use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\TableroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| La Carrera de Multiplicar
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('alumno'));

// Tablet del alumno.
Route::get('/alumno', [AlumnoController::class, 'pantalla'])->name('alumno');
Route::post('/alumno/unirse', [AlumnoController::class, 'unirse'])->name('alumno.unirse');

// Estas rutas necesitan el token del alumno (header X-Alumno-Token).
Route::middleware('auth:alumno')->group(function () {
    Route::get('/alumno/estado', [AlumnoController::class, 'estado'])->name('alumno.estado');
    Route::post('/alumno/responder', [AlumnoController::class, 'responder'])->name('alumno.responder');
});

// Proyector de la docente.
Route::get('/tablero', [TableroController::class, 'pantalla'])->name('tablero');
Route::get('/tablero/estado', [TableroController::class, 'estado'])->name('tablero.estado');

// Ingreso con clave (para usar los controles por internet).
// throttle:5,1 = como máximo 5 intentos por minuto, para que no prueben claves a lo loco.
Route::post('/docente/entrar', [DocenteController::class, 'entrar'])
    ->middleware('throttle:5,1')
    ->name('docente.entrar');
Route::post('/docente/salir', [DocenteController::class, 'salir'])->name('docente.salir');

// Controles: localhost o docente con clave (guard "docente").
Route::middleware('auth:docente')->group(function () {
    Route::post('/tablero/iniciar', [TableroController::class, 'iniciar'])->name('tablero.iniciar');
    Route::post('/tablero/reiniciar', [TableroController::class, 'reiniciar'])->name('tablero.reiniciar');
    Route::get('/tablero/errores', [TableroController::class, 'errores'])->name('tablero.errores');
});

/*
|--------------------------------------------------------------------------
| Demo anterior (emisor / receptor)
|--------------------------------------------------------------------------
*/

Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');
