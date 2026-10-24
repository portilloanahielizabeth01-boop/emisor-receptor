/**
 * alumno.js — Lógica de la tablet del alumno.
 *
 * Resumen del flujo:
 *   1. El chico se une (HTTP POST) y recibe un TOKEN secreto.
 *   2. Con ese token pedimos el estado (HTTP GET) y abrimos el WebSocket.
 *   3. Nos suscribimos a:
 *        - "alumno.{id}" (privado): NUEVA_PREGUNTA, RESULTADO_RESPUESTA
 *        - "carrera"     (público): INICIAR_JUEGO, ACTUALIZAR_TABLERO,
 *                                   MISION_CUMPLIDA, REINICIAR_JUEGO
 *        - "aula"        (presencia): para que el proyector sepa que estamos
 *   4. Al tocar una opción mandamos ENVIAR_RESPUESTA (HTTP POST) y
 *      esperamos que el servidor nos diga por WebSocket si estaba bien.
 *      Si estaba mal, se marca el error y llega sola una pregunta de otra tabla.
 */
import { alCambiarConexion, crearEcho, pedir } from './conexion';
import { confettiChico, confettiGrande, desbloquearAudio, sonidoAcierto, sonidoMision } from './efectos';

// ---------------------------------------------------------------------------
// Estado de la pantalla
// ---------------------------------------------------------------------------

const CLAVE_TOKEN = 'carrera.token';
const CLAVE_ALUMNO = 'carrera.alumno';

const estado = {
    token: null,
    alumno: null, // { id, nombre, avatar, aciertos }
    pregunta: null, // { a, b, opciones }
    preguntaPendiente: null, // llegó una pregunta nueva mientras se mostraba el resultado
    mostrandoResultado: false, // "¡Muy bien!" o el error marcado, durante un momento
    esperandoResultado: false,
    temporizadorResultado: null,
    echo: null,
};

/** Atajo para buscar elementos por id. */
const $ = (id) => document.getElementById(id);

// ---------------------------------------------------------------------------
// Guardar / recordar al alumno en el navegador
// ---------------------------------------------------------------------------
//
// sessionStorage es de CADA PESTAÑA y sobrevive a recargar la página:
//   → si el chico recarga, sigue siendo el mismo alumno.
//   → si abrimos otra pestaña (para probar), es un alumno distinto.
// localStorage se comparte en todo el navegador y sobrevive a cerrarlo:
//   → lo usamos solo para preguntar "¿Sos Juan?" al volver a entrar.

function guardarAlumno(token, alumno) {
    sessionStorage.setItem(CLAVE_TOKEN, token);
    localStorage.setItem(CLAVE_TOKEN, token);
    localStorage.setItem(CLAVE_ALUMNO, JSON.stringify(alumno));
}

function olvidarAlumno() {
    sessionStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_ALUMNO);
}

// ---------------------------------------------------------------------------
// Mostrar pantallas
// ---------------------------------------------------------------------------

function mostrarPantalla(nombre) {
    document.querySelectorAll('.pantalla').forEach((seccion) => {
        seccion.hidden = seccion.id !== `pantalla-${nombre}`;
    });
}

/** Rellena todos los lugares donde aparece el nombre, avatar y estrellas. */
function pintarDatosAlumno() {
    document.querySelectorAll('[data-mi-nombre]').forEach((el) => (el.textContent = estado.alumno.nombre));
    document.querySelectorAll('[data-mi-avatar]').forEach((el) => (el.textContent = estado.alumno.avatar));
    document.querySelectorAll('[data-mis-aciertos]').forEach((el) => (el.textContent = estado.alumno.aciertos));
}

function pintarMision(total, meta) {
    const porcentaje = Math.min(100, Math.round((total / meta) * 100));
    document.querySelector('[data-mision-relleno]').style.width = `${porcentaje}%`;
    document.querySelector('[data-mision-texto]').textContent = `${total} / ${meta} ⭐`;
}

// ---------------------------------------------------------------------------
// Inicio: ¿quién soy?
// ---------------------------------------------------------------------------

function iniciar() {
    const tokenDeEstaPestana = sessionStorage.getItem(CLAVE_TOKEN);
    const tokenRecordado = localStorage.getItem(CLAVE_TOKEN);
    const alumnoRecordado = JSON.parse(localStorage.getItem(CLAVE_ALUMNO) ?? 'null');

    if (tokenDeEstaPestana) {
        // Recargó la página: seguimos sin preguntar nada.
        conectar(tokenDeEstaPestana);
    } else if (tokenRecordado && alumnoRecordado) {
        // Volvió a abrir el navegador: le preguntamos si es él.
        $('volver-avatar').textContent = alumnoRecordado.avatar;
        $('volver-nombre').textContent = alumnoRecordado.nombre;
        mostrarPantalla('volver');
    } else {
        mostrarPantalla('unirse');
    }
}

$('boton-seguir').addEventListener('click', () => {
    desbloquearAudio();
    const token = localStorage.getItem(CLAVE_TOKEN);
    sessionStorage.setItem(CLAVE_TOKEN, token);
    conectar(token);
});

$('boton-soy-otro').addEventListener('click', () => mostrarPantalla('unirse'));

// UNIRSE ---------------------------------------------------------------------
$('form-unirse').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    desbloquearAudio(); // primer toque: habilitamos los sonidos

    const formulario = new FormData(evento.target);
    const boton = evento.target.querySelector('button');
    boton.disabled = true;
    $('error-unirse').hidden = true;

    try {
        const { status, datos } = await pedir('/alumno/unirse', {
            metodo: 'POST',
            datos: { nombre: formulario.get('nombre').trim(), avatar: formulario.get('avatar') },
        });

        if (status !== 201) {
            throw new Error(datos?.message ?? 'No se pudo entrar');
        }

        guardarAlumno(datos.token, datos.alumno);
        await conectar(datos.token);
    } catch (error) {
        $('error-unirse').textContent = '¡Ups! Revisá tu nombre e intentá otra vez.';
        $('error-unirse').hidden = false;
        console.error(error);
    } finally {
        boton.disabled = false;
    }
});

// ---------------------------------------------------------------------------
// Conectarse: estado inicial (HTTP) + WebSocket
// ---------------------------------------------------------------------------

function headersAlumno() {
    return { 'X-Alumno-Token': estado.token };
}

/**
 * Pide al servidor "¿cómo está todo?" y acomoda la pantalla.
 * Se usa al entrar y cada vez que el WebSocket se reconecta, porque los
 * mensajes que se mandaron mientras estábamos desconectados se perdieron.
 */
async function sincronizar() {
    const { status, datos } = await pedir('/alumno/estado', { headers: headersAlumno() });

    if (status === 401) {
        // El token ya no existe (la seño vació la pista): a empezar de nuevo.
        olvidarAlumno();
        window.location.reload();
        return;
    }

    estado.alumno = datos.alumno;
    pintarDatosAlumno();
    pintarMision(datos.partida.total_correctas, datos.partida.meta);

    if (datos.partida.estado === 'espera') {
        mostrarPantalla('espera');
    } else if (datos.partida.estado === 'terminada') {
        mostrarPantalla('mision');
    } else {
        mostrarPregunta(datos.pregunta);
    }
}

async function conectar(token) {
    estado.token = token;
    await sincronizar();

    // Si sincronizar() recargó la página, no seguimos.
    if (!estado.alumno) {
        return;
    }

    estado.echo = crearEcho(headersAlumno());
    escucharEventos(estado.echo);
    vigilarConexion(estado.echo);
}

function escucharEventos(echo) {
    // El punto inicial en '.NUEVA_PREGUNTA' le dice a Echo que use el nombre
    // tal cual lo definimos con broadcastAs() en Laravel.

    // Canal PRIVADO: solo para mí.
    echo.private(`alumno.${estado.alumno.id}`)
        .listen('.NUEVA_PREGUNTA', (e) => recibirPregunta(e.pregunta))
        .listen('.RESULTADO_RESPUESTA', (e) => mostrarResultado(e));

    // Canal PÚBLICO: lo que le pasa a todo el aula.
    echo.channel('carrera')
        .listen('.INICIAR_JUEGO', (e) => {
            estado.alumno.aciertos = 0;
            pintarDatosAlumno();
            pintarMision(0, e.meta);
            // La pregunta llega aparte, por el canal privado (NUEVA_PREGUNTA).
        })
        .listen('.ACTUALIZAR_TABLERO', (e) => {
            pintarMision(e.total_correctas, e.meta);
            const yo = e.alumnos.find((a) => a.id === estado.alumno.id);
            if (yo) {
                estado.alumno.aciertos = yo.aciertos;
                pintarDatosAlumno();
            }
        })
        .listen('.MISION_CUMPLIDA', () => {
            estado.pregunta = null;
            estado.preguntaPendiente = null;
            mostrarPantalla('mision');
            sonidoMision();
            confettiGrande(5);
        })
        .listen('.REINICIAR_JUEGO', (e) => {
            if (e.pista_vaciada) {
                olvidarAlumno();
                window.location.reload();
                return;
            }
            estado.pregunta = null;
            estado.alumno.aciertos = 0;
            pintarDatosAlumno();
            mostrarPantalla('espera');
        });

    // Canal de PRESENCIA: con solo entrar, el proyector ve que estoy conectado.
    echo.join('aula');
}

/** Muestra el cartel "Reconectando…" y se resincroniza al volver. */
function vigilarConexion(echo) {
    let yaSeConectoUnaVez = false;

    alCambiarConexion(echo, (actual) => {
        $('aviso-conexion').hidden = actual === 'connected';

        if (actual === 'connected') {
            if (yaSeConectoUnaVez) {
                sincronizar();
            }
            yaSeConectoUnaVez = true;
        }
    });
}

// ---------------------------------------------------------------------------
// Preguntas y respuestas
// ---------------------------------------------------------------------------

function recibirPregunta(pregunta) {
    // Si estamos mostrando el resultado ("¡Muy bien!" o el error), la guardamos para después.
    if (estado.mostrandoResultado) {
        estado.preguntaPendiente = pregunta;
        return;
    }
    mostrarPregunta(pregunta);
}

const COLORES_BOTONES = ['boton-rojo', 'boton-azul', 'boton-verde', 'boton-naranja'];

function mostrarPregunta(pregunta) {
    estado.pregunta = pregunta;
    estado.esperandoResultado = false;
    mostrarPantalla('juego');

    $('factor-a').textContent = pregunta.a;
    $('factor-b').textContent = pregunta.b;

    // Animación de entrada de la operación.
    $('operacion').classList.remove('entrar');
    void $('operacion').offsetWidth; // truco para reiniciar la animación CSS
    $('operacion').classList.add('entrar');

    // Creamos los 4 botones.
    const contenedor = $('opciones');
    contenedor.replaceChildren();

    pregunta.opciones.forEach((opcion, i) => {
        const boton = document.createElement('button');
        boton.className = `boton-opcion ${COLORES_BOTONES[i]}`;
        boton.textContent = opcion;
        boton.addEventListener('click', () => enviarRespuesta(opcion, boton));
        contenedor.append(boton);
    });
}

/** Bloquea o desbloquea los botones. */
function bloquearBotones(bloquear) {
    $('opciones').querySelectorAll('.boton-opcion').forEach((boton) => {
        boton.disabled = bloquear;
    });
}

// ENVIAR_RESPUESTA -----------------------------------------------------------
async function enviarRespuesta(opcion, boton) {
    // Evita mandar dos respuestas si toca muy rápido dos veces.
    if (estado.esperandoResultado) {
        return;
    }
    estado.esperandoResultado = true;
    bloquearBotones(true);
    boton.classList.add('elegida');

    // Plan B: si el resultado no llega por WebSocket en 5 segundos,
    // preguntamos al servidor cómo está todo.
    clearTimeout(estado.temporizadorResultado);
    estado.temporizadorResultado = setTimeout(sincronizar, 5000);

    try {
        // Mandamos SOLO qué opción eligió. Quién decide si está bien es el servidor.
        const { status } = await pedir('/alumno/responder', {
            metodo: 'POST',
            headers: headersAlumno(),
            datos: { a: estado.pregunta.a, b: estado.pregunta.b, respuesta: opcion },
        });

        if (status !== 202) {
            // 409: la pregunta ya no era esa, o el juego terminó → resincronizar.
            clearTimeout(estado.temporizadorResultado);
            await sincronizar();
        }
    } catch (error) {
        // Sin red: dejamos volver a intentar.
        console.error(error);
        clearTimeout(estado.temporizadorResultado);
        estado.esperandoResultado = false;
        boton.classList.remove('elegida');
        bloquearBotones(false);
    }
}

// RESULTADO_RESPUESTA --------------------------------------------------------
function mostrarResultado(resultado) {
    clearTimeout(estado.temporizadorResultado);
    estado.esperandoResultado = false;
    estado.alumno.aciertos = resultado.aciertos;
    pintarDatosAlumno();

    const botones = [...$('opciones').children];
    const boton = botones.find((b) => Number(b.textContent) === resultado.respuesta_elegida);
    boton?.classList.remove('elegida');

    if (resultado.es_correcta) {
        festejarAcierto(boton);
    } else {
        const botonCorrecto = botones.find((b) => Number(b.textContent) === resultado.respuesta_correcta);
        marcarError(boton, botonCorrecto, resultado.respuesta_correcta);
    }
}

function festejarAcierto(boton) {
    boton?.classList.add('correcta');
    sonidoAcierto();
    confettiChico();

    mostrarUnMomento($('festejo'), 1300);
}

/**
 * Se equivocó: marcamos el error de inmediato (la opción elegida en rojo
 * y la correcta resaltada) y, sin esperar a que toque nada, pasamos a la
 * pregunta siguiente, que el servidor ya mandó de OTRA tabla.
 */
function marcarError(boton, botonCorrecto, respuestaCorrecta) {
    boton?.classList.add('incorrecta');
    botonCorrecto?.classList.add('era-correcta');

    $('aviso-error-operacion').textContent = `${estado.pregunta.a} × ${estado.pregunta.b} = ${respuestaCorrecta}`;

    mostrarUnMomento($('aviso-error'), 1800);
}

/**
 * Muestra un cartel encima durante unos milisegundos y después pasa a la
 * pregunta que haya llegado mientras tanto.
 */
function mostrarUnMomento(cartel, milisegundos) {
    estado.mostrandoResultado = true;
    cartel.hidden = false;

    setTimeout(() => {
        estado.mostrandoResultado = false;
        cartel.hidden = true;

        if (estado.preguntaPendiente) {
            const siguiente = estado.preguntaPendiente;
            estado.preguntaPendiente = null;
            mostrarPregunta(siguiente);
        }
    }, milisegundos);
}

iniciar();
