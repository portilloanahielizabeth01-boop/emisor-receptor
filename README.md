# 🏁 La Carrera de Multiplicar

Trabajo práctico de **WebSockets**: un juego en tiempo real para repasar las tablas de multiplicar.

Cada alumno juega desde su tablet o celular y el proyector muestra una carrera donde su avatar avanza con cada respuesta correcta. Entre todos cooperan para cumplir la **Misión del Aula** (una meta de respuestas correctas del curso).

## Tecnologías

- **Laravel 13**: rutas, validación, base de datos (SQLite) y eventos
- **Laravel Reverb**: servidor de WebSockets
- **Laravel Echo + pusher-js**: cliente WebSocket en el navegador (JavaScript puro)
- **Blade + CSS**: pantallas del alumno y del tablero
- **PHPUnit**: tests automáticos

## Requisitos

- **PHP 8.4 o superior**, con las extensiones `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo` y `curl`
- **Composer** 2
- **Node.js 22** o superior

No hace falta instalar una base de datos: se usa SQLite (un archivo).

## Instalación

```bash
git clone https://github.com/portilloanahielizabeth01-boop/emisor-receptor.git
cd emisor-receptor
composer setup
```

`composer setup` instala las dependencias, crea el `.env`, genera la clave, crea la base de datos y compila el JavaScript y el CSS.

## Ejecutar

```bash
composer run carrera
```

Levanta los dos servidores juntos: la app en el puerto **8000** y Reverb en el **8080**. Se cortan con `Ctrl+C`.

| Pantalla | Dirección |
|---|---|
| Tablero (docente) | http://localhost:8000/tablero |
| Alumno | http://localhost:8000/alumno, o http://IP-de-la-PC:8000/alumno desde otro dispositivo (la dirección y el QR aparecen en el tablero) |

El tablero muestra los controles (Iniciar, Reiniciar, tablas y meta) solo si se abre con `localhost`.

### Probarlo en una sola computadora

1. Abrí http://localhost:8000/tablero en una ventana.
2. Abrí http://localhost:8000/alumno en otra pestaña, elegí nombre y avatar y tocá **¡A jugar!**. Para sumar más alumnos, abrí más pestañas y tocá **"No, soy otro chico"**.
3. En el tablero elegí una meta chica (por ejemplo 5) y tocá **▶️ Iniciar**.
4. Respondé desde las pestañas: los avatares avanzan en el tablero al instante.

Para jugar por internet se abren dos túneles de Cloudflare (uno por puerto) y se cargan sus direcciones en `APP_URL` y `REVERB_PUBLIC_HOST` del `.env`.

## Cómo funciona

1. El alumno **envía** sus acciones por HTTP (unirse, responder).
2. Laravel las valida, guarda el resultado y **publica** un evento en Reverb.
3. Reverb **empuja** el evento por WebSocket a todos los navegadores suscritos.

El servidor es el único que publica: así un alumno no puede mandar "acerté" sin haber acertado.

### Canales

| Canal | Tipo | Para qué |
|---|---|---|
| `carrera` | Público | Tablero, inicio, reinicio y misión cumplida, para todos |
| `alumno.{id}` | Privado | Las preguntas y resultados de cada alumno |
| `docente` | Privado | El reporte de errores en vivo, solo para la docente |
| `aula` | Presencia | Saber quién está conectado (`here`, `joining`, `leaving`) |

### Eventos

`AlumnoUnido`, `NuevaPregunta`, `ResultadoRespuesta`, `ErrorRegistrado`, `TableroActualizado`, `JuegoIniciado`, `MisionCumplida` y `JuegoReiniciado`, en [app/Events/](app/Events/).

## Tests

```bash
php artisan test
```

## Mi Anotación.

La estructura base del proyecto la armé yo sola, siguiendo la guía del profesor sobre emisor-receptor con WebSockets. A partir de esa base fui modificando el proyecto hasta convertirlo en el juego de la carrera.

Para otra etapa usé la asistencia de **Claude Code** (IA de Anthropic), que me ayudo y me explico a generar parte del código del juego. Yo definí qué necesitaba el juego y me encargué de la puesta en marcha:

- **Conexión de las tablets en la red del aula:** levantar los servidores para que acepten conexiones de la red y conectar los dispositivos por wifi con la dirección y el QR del tablero para usarlo con un grupo de alumnos en mi trabajo.
- **Tunelización con Cloudflare Tunnel:** instalar `cloudflared`, abrir los dos túneles (uno para la app y otro para Reverb) y configurar el `.env` con las direcciones públicas, para que mis compañeros pudieran jugar desde sus casas.
- **Pruebas con mis compañeros:** probar el juego en una clase virtual y verificar que los avatares y la Misión del Aula se actualizaran en tiempo real.
