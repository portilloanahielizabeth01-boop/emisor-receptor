<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    {{-- user-scalable=no: evita que el zoom con doble toque moleste a los chicos en la tablet --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    {{-- Token CSRF: JavaScript lo lee para que Laravel acepte los POST --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo') · La Carrera de Multiplicar</title>
    {{-- Dirección del WebSocket para Echo. La calcula el servidor (App\Services\ConexionReverb),
         así cambiar el túnel no obliga a recompilar el JavaScript. Tiene que ir ANTES de @vite. --}}
    <script>window.REVERB = @json($reverbNavegador);</script>
    @vite(['resources/css/carrera.css', $script])
</head>
<body class="@yield('clase-body')">
    {{-- Aviso que aparece si se corta el WebSocket (pusher-js reconecta solo) --}}
    <div id="aviso-conexion" class="aviso-conexion" hidden>🔌 Reconectando…</div>

    @yield('contenido')
</body>
</html>
