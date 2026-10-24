/**
 * tablero.js — Lógica de la pantalla del proyector.
 *
 * Escucha:
 *   - "carrera" (público): UNIRSE, ACTUALIZAR_TABLERO, INICIAR_JUEGO,
 *                          MISION_CUMPLIDA, REINICIAR_JUEGO
 *   - "aula" (presencia):  quién está conectado (here / joining / leaving)
 *   - "docente" (privado): ERROR_REGISTRADO (solo en la compu de la docente)
 *
 * Y si es la computadora de la docente, manda los controles por HTTP POST.
 */
import QRCode from 'qrcode';
import { alCambiarConexion, crearEcho, pedir } from './conexion';
import { confettiGrande, desbloquearAudio, sonidoMision } from './efectos';

const { resumen: resumenInicial, urlAula, esDocente, reporteErrores: reporteInicial, casilleros } = window.CARRERA;

const $ = (id) => document.getElementById(id);

/** Último resumen recibido del servidor. */
let resumen = resumenInicial;

/** Reporte de errores (null si no es la compu de la docente). */
let reporteErrores = reporteInicial;

/** Cuántos errores de cada alumno se listan (igual que Respuesta::ERRORES_POR_ALUMNO). */
const ERRORES_POR_ALUMNO = 20;

const REPORTE_VACIO = { total: 0, por_tabla: [], por_alumno: [] };

/** IDs de los alumnos conectados ahora (los informa el canal de presencia). */
const conectados = new Set();

const TEXTO_ESTADOS = {
    espera: '⏳ Sala de espera',
    jugando: '🏎️ ¡En carrera!',
    terminada: '🏆 ¡Misión cumplida!',
};

// ---------------------------------------------------------------------------
// Dibujar
// ---------------------------------------------------------------------------

function pintarTodo() {
    $('estado-juego').textContent = TEXTO_ESTADOS[resumen.estado];

    const porcentaje = Math.min(100, Math.round((resumen.total_correctas / resumen.meta) * 100));
    $('mision-relleno').style.width = `${porcentaje}%`;
    $('mision-texto').textContent = `${resumen.total_correctas} / ${resumen.meta} ⭐`;

    pintarPista();
}

/**
 * Crea / actualiza / borra los carriles de la pista.
 * Cada carril se identifica con data-id para no recrearlo cada vez
 * (si lo recreáramos, se perdería la animación de avance).
 */
function pintarPista() {
    const pista = $('pista');
    const idsActuales = new Set(resumen.alumnos.map((a) => String(a.id)));

    // Borramos carriles de alumnos que ya no existen.
    pista.querySelectorAll('.carril').forEach((carril) => {
        if (!idsActuales.has(carril.dataset.id)) {
            carril.remove();
        }
    });

    resumen.alumnos.forEach((alumno) => {
        let carril = pista.querySelector(`.carril[data-id="${alumno.id}"]`);

        if (!carril) {
            carril = crearCarril(alumno);
            pista.append(carril);
        }

        actualizarCarril(carril, alumno);
    });

    $('pista-vacia').hidden = resumen.alumnos.length > 0;
}

function crearCarril(alumno) {
    const carril = document.createElement('div');
    carril.className = 'carril';
    carril.dataset.id = alumno.id;
    carril.dataset.aciertos = '0';

    // IMPORTANTE: usamos textContent (no innerHTML) para el nombre.
    // Si un chico escribe "<script>" como nombre, se muestra como texto
    // y no se ejecuta (evita inyección de código / XSS).
    const nombre = document.createElement('span');
    nombre.className = 'carril-nombre';
    nombre.textContent = alumno.nombre;

    const camino = document.createElement('div');
    camino.className = 'carril-camino';

    const corredor = document.createElement('span');
    corredor.className = 'corredor';
    corredor.textContent = alumno.avatar;

    const puntos = document.createElement('span');
    puntos.className = 'carril-puntos';

    camino.append(corredor);
    carril.append(nombre, camino, puntos);

    return carril;
}

function actualizarCarril(carril, alumno) {
    const corredor = carril.querySelector('.corredor');
    const avanzo = alumno.aciertos > Number(carril.dataset.aciertos);

    // Posición: un casillero por acierto; al llegar al final queda en la meta.
    const posicion = Math.min(alumno.aciertos, casilleros) / casilleros;
    corredor.style.setProperty('--avance', posicion);

    carril.querySelector('.carril-puntos').textContent = `⭐ ${alumno.aciertos}`;
    carril.dataset.aciertos = alumno.aciertos;
    carril.classList.toggle('dormido', !conectados.has(alumno.id));
    carril.classList.toggle('llego', alumno.aciertos >= casilleros);

    // Saltito cuando avanza.
    if (avanzo) {
        corredor.classList.remove('salto');
        void corredor.offsetWidth;
        corredor.classList.add('salto');
    }
}

/** Solo actualiza el "dormido/despierto" de cada carril. */
function pintarConectados() {
    $('pista').querySelectorAll('.carril').forEach((carril) => {
        carril.classList.toggle('dormido', !conectados.has(Number(carril.dataset.id)));
    });
}

/**
 * Suma al reporte el error que acaba de llegar por WebSocket (ERROR_REGISTRADO).
 * El servidor manda solo el error nuevo; el reporte completo se pide por HTTP
 * al abrir la página y al reconectarse.
 */
function sumarError({ alumno, error }) {
    reporteErrores.total += 1;

    const tabla = reporteErrores.por_tabla.find((t) => t.tabla === error.a);
    if (tabla) {
        tabla.errores += 1;
    } else {
        reporteErrores.por_tabla.push({ tabla: error.a, errores: 1 });
    }
    reporteErrores.por_tabla.sort((x, y) => y.errores - x.errores || x.tabla - y.tabla);

    let delAlumno = reporteErrores.por_alumno.find((a) => a.id === alumno.id);
    if (!delAlumno) {
        delAlumno = { ...alumno, total_errores: 0, errores: [] };
        reporteErrores.por_alumno.push(delAlumno);
    }
    delAlumno.total_errores += 1;
    delAlumno.errores = [error, ...delAlumno.errores].slice(0, ERRORES_POR_ALUMNO);
    reporteErrores.por_alumno.sort((x, y) => y.total_errores - x.total_errores);

    pintarErrores();
}

/**
 * Reporte de errores de la docente: cuántos errores hubo en cada tabla y,
 * por alumno, qué operación exacta respondió mal y qué contestó.
 */
function pintarErrores() {
    if (!reporteErrores) {
        return;
    }

    $('errores-total').textContent = reporteErrores.total;
    $('errores-vacio').hidden = reporteErrores.total > 0;

    const porTabla = $('errores-por-tabla');
    porTabla.replaceChildren(
        ...reporteErrores.por_tabla.map(({ tabla, errores }) => {
            const chip = document.createElement('span');
            chip.className = 'chip-tabla-error';
            chip.textContent = `Tabla del ${tabla}: ${errores} ✗`;
            return chip;
        }),
    );

    const porAlumno = $('errores-por-alumno');
    porAlumno.replaceChildren(
        ...reporteErrores.por_alumno.map((alumno) => {
            const fila = document.createElement('div');
            fila.className = 'fila-errores-alumno';

            // textContent (no innerHTML) para el nombre, igual que en la pista.
            const nombre = document.createElement('span');
            nombre.className = 'errores-alumno-nombre';
            nombre.textContent = `${alumno.avatar} ${alumno.nombre} (${alumno.total_errores})`;

            const operaciones = document.createElement('div');
            operaciones.className = 'errores-operaciones';
            alumno.errores.forEach((error) => {
                const operacion = document.createElement('span');
                operacion.className = 'chip-operacion-error';
                operacion.title = `Tabla del ${error.a} · ${error.hora}`;
                operacion.textContent = `${error.a} × ${error.b} = ${error.respuesta_correcta} · dijo ${error.respuesta_elegida}`;
                operaciones.append(operacion);
            });

            fila.append(nombre, operaciones);
            return fila;
        }),
    );
}

function mostrarAviso(texto) {
    const aviso = document.createElement('div');
    aviso.className = 'aviso';
    aviso.textContent = texto;
    $('avisos').append(aviso);
    setTimeout(() => aviso.remove(), 3500);
}

function festejarMision() {
    $('overlay-mision-texto').textContent = `Entre todos sumaron ${resumen.total_correctas} respuestas correctas 🎉`;
    $('overlay-mision').hidden = false;
    sonidoMision();
    confettiGrande(6);
}

// ---------------------------------------------------------------------------
// WebSocket
// ---------------------------------------------------------------------------

const echo = crearEcho();

echo.channel('carrera')
    .listen('.UNIRSE', (e) => mostrarAviso(`¡Llegó ${e.alumno.nombre} ${e.alumno.avatar}!`))
    .listen('.ACTUALIZAR_TABLERO', (e) => {
        resumen = e;
        pintarTodo();
    })
    .listen('.INICIAR_JUEGO', () => {
        $('overlay-mision').hidden = true;
        mostrarAviso('🚦 ¡Largó la carrera!');
    })
    .listen('.MISION_CUMPLIDA', (e) => {
        resumen.total_correctas = e.total_correctas;
        festejarMision();
    })
    .listen('.REINICIAR_JUEGO', (e) => {
        $('overlay-mision').hidden = true;

        // "Nueva clase": se borraron los alumnos y, con ellos, sus errores.
        if (e.pista_vaciada && reporteErrores) {
            reporteErrores = structuredClone(REPORTE_VACIO);
            pintarErrores();
        }
    });

// Canal de PRESENCIA: Reverb nos dice quién está conectado.
echo.join('aula')
    .here((miembros) => {
        // Lista completa al entrar (o al reconectarnos).
        conectados.clear();
        miembros.filter((m) => m.tipo === 'alumno').forEach((m) => conectados.add(m.id));
        pintarConectados();
    })
    .joining((miembro) => {
        if (miembro.tipo === 'alumno') {
            conectados.add(miembro.id);
            pintarConectados();
        }
    })
    .leaving((miembro) => {
        // Cerró la tablet o se cortó el wifi: el avatar se "duerme" 😴,
        // pero NO se borra: si vuelve, sigue con sus estrellas.
        if (miembro.tipo === 'alumno') {
            conectados.delete(miembro.id);
            pintarConectados();
        }
    });

// Canal PRIVADO de la docente: el reporte de errores. Las tablets no pueden entrar.
if (esDocente) {
    echo.private('docente').listen('.ERROR_REGISTRADO', (e) => sumarError(e));
}

// Al reconectarse, pedimos la foto actual por si nos perdimos mensajes.
let yaSeConectoUnaVez = false;
alCambiarConexion(echo, async (actual) => {
    $('aviso-conexion').hidden = actual === 'connected';

    if (actual === 'connected') {
        if (yaSeConectoUnaVez) {
            const { datos } = await pedir('/tablero/estado');
            resumen = datos;
            pintarTodo();

            if (esDocente) {
                const { datos: errores } = await pedir('/tablero/errores');
                reporteErrores = errores;
                pintarErrores();
            }
        }
        yaSeConectoUnaVez = true;
    }
});

// ---------------------------------------------------------------------------
// QR y controles de la docente
// ---------------------------------------------------------------------------

QRCode.toCanvas($('qr'), urlAula, { width: 220, margin: 1 });

async function enviarControl(url, datos) {
    desbloquearAudio();
    $('mensaje-control').hidden = true;

    const { status, datos: respuesta } = await pedir(url, { metodo: 'POST', datos });

    if (status !== 200) {
        $('mensaje-control').textContent = respuesta?.message ?? 'No se pudo completar la acción.';
        $('mensaje-control').hidden = false;
    }
}

if (esDocente) {
    $('boton-iniciar').addEventListener('click', () => {
        const tablas = [...document.querySelectorAll('#selector-tablas input:checked')].map((c) => Number(c.value));

        if (tablas.length === 0) {
            $('mensaje-control').textContent = 'Elegí al menos una tabla.';
            $('mensaje-control').hidden = false;
            return;
        }

        enviarControl('/tablero/iniciar', { tablas, meta: Number($('campo-meta').value) });
    });

    $('boton-reiniciar').addEventListener('click', () => {
        enviarControl('/tablero/reiniciar', { vaciar_pista: false });
    });

    $('boton-vaciar').addEventListener('click', () => {
        if (confirm('¿Borrar a todos los corredores para empezar con otra clase?')) {
            enviarControl('/tablero/reiniciar', { vaciar_pista: true });
        }
    });
}

$('boton-cerrar-mision').addEventListener('click', () => {
    $('overlay-mision').hidden = true;
});

pintarTodo();
pintarErrores();
