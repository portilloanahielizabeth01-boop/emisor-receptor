{{--
    Pantalla del PROYECTOR (docente).

    Los datos iniciales se pasan a JavaScript en window.CARRERA; a partir de
    ahí, todo se actualiza en vivo por WebSocket (resources/js/carrera/tablero.js).
--}}
@extends('layouts.carrera', ['script' => 'resources/js/carrera/tablero.js'])

@section('titulo', 'Tablero')
@section('clase-body', 'pagina-tablero')

@section('contenido')
    <script>
        window.CARRERA = {
            resumen: @json($resumen),
            urlAula: @json($urlAula),
            esDocente: @json($esDocente),
            reporteErrores: @json($reporteErrores),
            casilleros: @json($casilleros),
        };
    </script>

    <header class="tablero-encabezado">
        <h1 class="titulo-juego">🏁 La Carrera de Multiplicar</h1>
        <span id="estado-juego" class="chip-estado"></span>
    </header>

    <main class="tablero-cuerpo">
        <section class="tablero-principal">
            {{-- Misión del Aula (cooperativa) --}}
            <div class="mision">
                <div class="mision-titulo">🚀 Misión del Aula</div>
                <div class="mision-barra">
                    <div id="mision-relleno" class="mision-relleno"></div>
                    <span id="mision-texto" class="mision-texto"></span>
                </div>
            </div>

            {{-- La pista: un carril por alumno --}}
            <div id="pista" class="pista">
                <p id="pista-vacia" class="pista-vacia">Esperando corredores… ¡Escaneen el código! 📱</p>
            </div>

            {{-- Reporte de errores: solo en la compu de la docente. Se puede plegar
                 para que no se vea en el proyector. Lo dibuja tablero.js. --}}
            @if ($esDocente)
                <details id="reporte-errores" class="tarjeta reporte-errores" open>
                    <summary class="reporte-titulo">
                        📋 Errores para revisar <span id="errores-total" class="chip-total"></span>
                        <span class="reporte-nota">(solo docente · tocá para ocultar)</span>
                    </summary>

                    <p class="etiqueta">Errores por tabla</p>
                    <div id="errores-por-tabla" class="errores-por-tabla"></div>

                    <p class="etiqueta">Errores por alumno</p>
                    <div id="errores-por-alumno" class="errores-por-alumno"></div>

                    <p id="errores-vacio" class="pista-vacia">Todavía nadie se equivocó 👏</p>
                </details>
            @endif
        </section>

        <aside class="tablero-lateral">
            {{-- Cómo entrar desde la tablet --}}
            <div class="tarjeta tarjeta-qr">
                <p class="etiqueta">Entrá desde tu tablet</p>
                <canvas id="qr"></canvas>
                <p class="url-aula">{{ $urlAula }}</p>
            </div>

            {{-- Controles: solo se muestran en la compu de la docente --}}
            @if ($esDocente)
                <div class="tarjeta controles">
                    <p class="etiqueta">Tablas a practicar</p>
                    <div id="selector-tablas" class="selector-tablas">
                        @foreach (range(1, 10) as $tabla)
                            <label class="opcion-tabla">
                                <input type="checkbox" value="{{ $tabla }}" @checked(in_array($tabla, $resumen['tablas']))>
                                <span>{{ $tabla }}</span>
                            </label>
                        @endforeach
                    </div>

                    <label class="etiqueta" for="campo-meta">Meta del aula</label>
                    <input id="campo-meta" class="campo-meta" type="number" min="5" max="500" value="{{ $resumen['meta'] }}">

                    <button id="boton-iniciar" class="boton-control boton-verde">▶️ Iniciar</button>
                    <button id="boton-reiniciar" class="boton-control boton-naranja">🔄 Reiniciar</button>
                    <button id="boton-vaciar" class="boton-control boton-gris">🧹 Nueva clase (vaciar pista)</button>
                    <p id="mensaje-control" class="mensaje-error" hidden></p>

                    {{-- Solo si entró con clave (por localhost no hay nada de qué "salir") --}}
                    @if (session('es_docente'))
                        <form method="POST" action="{{ route('docente.salir') }}">
                            @csrf
                            <button type="submit" class="boton-chico">Salir del modo docente</button>
                        </form>
                    @endif
                </div>
            @elseif ($hayClaveDocente)
                {{-- Por internet: la docente se identifica con la clave del .env (DOCENTE_CLAVE) --}}
                <form method="POST" action="{{ route('docente.entrar') }}" class="tarjeta controles">
                    @csrf
                    <label class="etiqueta" for="campo-clave">🔑 Soy la docente</label>
                    <input id="campo-clave" class="campo-meta" type="password" name="clave" required autocomplete="current-password" placeholder="Clave">
                    @error('clave')
                        <p class="mensaje-error">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="boton-control boton-azul">Entrar</button>
                </form>
            @else
                <p class="nota-docente">Los controles aparecen solo en la computadora de la docente (localhost).</p>
            @endif
        </aside>
    </main>

    {{-- Aviso "¡Llegó Juan!" --}}
    <div id="avisos" class="avisos"></div>

    {{-- Festejo final --}}
    <div id="overlay-mision" class="overlay-mision" hidden>
        <div class="overlay-mision-contenido">
            <div class="avatar-gigante">🏆</div>
            <h2>¡MISIÓN CUMPLIDA!</h2>
            <p id="overlay-mision-texto"></p>
            <button id="boton-cerrar-mision" class="boton-control boton-azul">Cerrar</button>
        </div>
    </div>
@endsection
