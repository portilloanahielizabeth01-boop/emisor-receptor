{{--
    Pantalla del ALUMNO (tablet).

    Tiene varias "pantallas" (secciones) y JavaScript muestra una sola a la vez
    con el atributo hidden. Toda la lógica está en resources/js/carrera/alumno.js.
--}}
@extends('layouts.carrera', ['script' => 'resources/js/carrera/alumno.js'])

@section('titulo', 'Alumno')
@section('clase-body', 'pagina-alumno')

@section('contenido')

    {{-- 1) Pantalla para unirse: nombre + avatar --}}
    <section id="pantalla-unirse" class="pantalla" hidden>
        <h1 class="titulo-juego">🏁 La Carrera de Multiplicar</h1>

        <form id="form-unirse" class="tarjeta" autocomplete="off">
            <label class="etiqueta" for="campo-nombre">¿Cómo te llamás?</label>
            <input id="campo-nombre" class="campo-nombre" name="nombre" maxlength="20" required placeholder="Tu nombre">

            <p class="etiqueta">Elegí tu corredor</p>
            <div class="grilla-avatares">
                @foreach ($avatares as $avatar)
                    <label class="opcion-avatar">
                        <input type="radio" name="avatar" value="{{ $avatar }}" @checked($loop->first)>
                        <span>{{ $avatar }}</span>
                    </label>
                @endforeach
            </div>

            <p id="error-unirse" class="mensaje-error" hidden></p>
            <button type="submit" class="boton-grande boton-verde">¡A jugar! 🚦</button>
        </form>
    </section>

    {{-- 2) "¿Sos vos?": la tablet recuerda al último alumno (si se cerró el navegador) --}}
    <section id="pantalla-volver" class="pantalla" hidden>
        <div class="tarjeta tarjeta-centrada">
            <div id="volver-avatar" class="avatar-gigante"></div>
            <p class="texto-grande">¿Sos <strong id="volver-nombre"></strong>?</p>
            <button id="boton-seguir" class="boton-grande boton-verde">¡Sí, seguir jugando!</button>
            <button id="boton-soy-otro" class="boton-chico">No, soy otro chico</button>
        </div>
    </section>

    {{-- 3) Sala de espera: esperando que la docente toque "Iniciar" --}}
    <section id="pantalla-espera" class="pantalla" hidden>
        <div class="tarjeta tarjeta-centrada">
            <div class="avatar-gigante avatar-esperando" data-mi-avatar></div>
            <p class="texto-grande">¡Hola, <strong data-mi-nombre></strong>!</p>
            <p class="texto-mediano">Esperando que la seño largue la carrera… 🚦</p>
        </div>
    </section>

    {{-- 4) El juego --}}
    <section id="pantalla-juego" class="pantalla" hidden>
        <header class="barra-alumno">
            <span class="chip-alumno"><span data-mi-avatar></span> <span data-mi-nombre></span></span>
            <span class="chip-aciertos">⭐ <span data-mis-aciertos>0</span></span>
        </header>

        {{-- Barra de la misión del aula (cooperativa) --}}
        <div class="mision-mini" aria-label="Misión del aula">
            <div class="mision-mini-relleno" data-mision-relleno></div>
            <span class="mision-mini-texto">Misión del aula: <span data-mision-texto></span></span>
        </div>

        <div id="operacion" class="operacion">
            <span id="factor-a"></span> × <span id="factor-b"></span> = <span class="signo">?</span>
        </div>

        <div id="opciones" class="opciones">
            {{-- Los 4 botones se crean desde JavaScript con cada pregunta --}}
        </div>

        {{-- Aviso al equivocarse: marca el error un momento y sigue con otra tabla --}}
        <div id="aviso-error" class="aviso-error" hidden>
            <div class="aviso-error-contenido">
                <span id="aviso-error-operacion"></span><br>
                <span class="aviso-error-sigue">¡Vamos con otra! 💪</span>
            </div>
        </div>

        {{-- Festejo al acertar (aparece encima un momento) --}}
        <div id="festejo" class="festejo" hidden>
            <div class="festejo-contenido">🎉<br>¡Muy bien!</div>
        </div>
    </section>

    {{-- 5) Misión cumplida --}}
    <section id="pantalla-mision" class="pantalla" hidden>
        <div class="tarjeta tarjeta-centrada mision-cumplida">
            <div class="avatar-gigante">🏆</div>
            <h2 class="titulo-juego">¡MISIÓN CUMPLIDA!</h2>
            <p class="texto-grande">Entre todos lo lograron 🎉</p>
            <p class="texto-mediano">Vos sumaste <strong data-mis-aciertos>0</strong> ⭐</p>
        </div>
    </section>

@endsection
