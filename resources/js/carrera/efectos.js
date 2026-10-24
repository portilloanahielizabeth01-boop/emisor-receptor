/**
 * efectos.js — Confetti y sonidos de festejo.
 *
 * Todo funciona SIN internet: el confetti viene del paquete npm
 * "canvas-confetti" (Vite lo incluye en el archivo final) y los sonidos
 * se fabrican en el momento con la Web Audio API (no hay archivos .mp3).
 */
import confetti from 'canvas-confetti';

let contextoAudio = null;

/**
 * Los navegadores (sobre todo en tablets) NO dejan reproducir sonido hasta
 * que el usuario toca la pantalla. Llamamos a esto en el primer toque
 * (por ejemplo, al apretar "¡A jugar!") para "destrabar" el audio.
 */
export function desbloquearAudio() {
    if (!contextoAudio) {
        const ContextoAudio = window.AudioContext || window.webkitAudioContext;
        if (!ContextoAudio) {
            return;
        }
        contextoAudio = new ContextoAudio();
    }

    if (contextoAudio.state === 'suspended') {
        contextoAudio.resume();
    }
}

/**
 * Toca una nota suave (onda senoidal que se apaga de a poco).
 */
function tocarNota(frecuencia, inicio, duracion, volumen = 0.15) {
    const oscilador = contextoAudio.createOscillator();
    const ganancia = contextoAudio.createGain();

    oscilador.type = 'sine';
    oscilador.frequency.value = frecuencia;

    // Volumen: sube rápido y baja despacio, para que no "golpee" el oído.
    ganancia.gain.setValueAtTime(0, inicio);
    ganancia.gain.linearRampToValueAtTime(volumen, inicio + 0.02);
    ganancia.gain.exponentialRampToValueAtTime(0.001, inicio + duracion);

    oscilador.connect(ganancia).connect(contextoAudio.destination);
    oscilador.start(inicio);
    oscilador.stop(inicio + duracion);
}

/**
 * "¡Ding-ding-ding!" corto para cada respuesta correcta (Do - Mi - Sol).
 */
export function sonidoAcierto() {
    if (!contextoAudio) {
        return;
    }
    const ahora = contextoAudio.currentTime;
    [523.25, 659.25, 783.99].forEach((nota, i) => tocarNota(nota, ahora + i * 0.09, 0.35));
}

/**
 * Melodía más larga para la misión cumplida.
 */
export function sonidoMision() {
    if (!contextoAudio) {
        return;
    }
    const ahora = contextoAudio.currentTime;
    const melodia = [523.25, 659.25, 783.99, 1046.5, 783.99, 1046.5];
    melodia.forEach((nota, i) => tocarNota(nota, ahora + i * 0.15, 0.5, 0.18));
}

/**
 * Un poquito de confetti (para cada acierto en la tablet).
 */
export function confettiChico() {
    confetti({ particleCount: 60, spread: 70, origin: { y: 0.7 } });
}

/**
 * Lluvia de confetti durante unos segundos (misión cumplida).
 */
export function confettiGrande(segundos = 4) {
    const fin = Date.now() + segundos * 1000;

    (function cuadro() {
        // Dos "cañones", uno de cada lado de la pantalla.
        confetti({ particleCount: 6, angle: 60, spread: 60, origin: { x: 0, y: 0.8 } });
        confetti({ particleCount: 6, angle: 120, spread: 60, origin: { x: 1, y: 0.8 } });

        if (Date.now() < fin) {
            requestAnimationFrame(cuadro);
        }
    })();
}
