/*
 * Alarme de pedido novo do painel: toca a cada poucos segundos enquanto houver pedido do
 * cardápio online esperando aceite. É um só por aba e continua tocando ao trocar de tela
 * (wire:navigate). O som fica ligado ou desligado por aparelho (localStorage).
 */
const STORAGE_KEY = 'order-alarm-sound';
const REPEAT_MS = 8000;

let ctx = null;
const state = { waiting: 0, newest: 0, silencedUpTo: 0, timer: null };

function soundEnabled() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== '0';
    } catch (e) {
        return true;
    }
}

function setSoundEnabled(on) {
    try {
        localStorage.setItem(STORAGE_KEY, on ? '1' : '0');
    } catch (e) { /* sem armazenamento: vale só nesta aba */ }
}

// O navegador só libera som depois de um clique ou tecla na página.
// Ao liberar, o pedido que já estava esperando toca na hora (o toque anterior foi mudo).
function unlock() {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx || (ctx && ctx.state === 'running')) return Promise.resolve();
    ctx = ctx || new AudioCtx();

    return (ctx.state === 'suspended' ? ctx.resume() : Promise.resolve())
        .then(() => {
            document.dispatchEvent(new CustomEvent('order-alarm-changed'));
            tick();
        })
        .catch(() => {});
}
['pointerdown', 'keydown'].forEach((type) => document.addEventListener(type, unlock, { capture: true, passive: true }));

const isBlocked = () => !ctx || ctx.state !== 'running';

function tones(notes, volume) {
    if (!soundEnabled() || isBlocked()) return;
    notes.forEach(([frequency, at, length]) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.value = frequency;
        osc.connect(gain);
        gain.connect(ctx.destination);
        const start = ctx.currentTime + at;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
        osc.start(start);
        osc.stop(start + length + 0.05);
    });
}

// Pedido online esperando: três notas subindo, repetidas até alguém aceitar.
const chime = () => tones([[660, 0, 0.18], [880, 0.2, 0.18], [1320, 0.4, 0.35]], 0.35);
// Outros pedidos novos (mesa, balcão): bipe curto, uma vez.
const beep = () => tones([[880, 0, 0.15], [880, 0.25, 0.15]], 0.25);

const silenced = () => state.newest > 0 && state.silencedUpTo >= state.newest;

function tick() {
    if (state.waiting > 0 && !silenced()) chime();
}

function update({ waiting, newest }) {
    const arrived = newest > state.newest;
    state.waiting = waiting;
    state.newest = newest;

    if (waiting > 0 && !state.timer) state.timer = setInterval(tick, REPEAT_MS);
    if (waiting === 0 && state.timer) {
        clearInterval(state.timer);
        state.timer = null;
    }
    if (arrived) tick();

    // "(2) Painel de pedidos": dá para ver na aba mesmo com outra janela na frente.
    const title = document.title.replace(/^\(\d+\) /, '');
    document.title = waiting > 0 ? `(${waiting}) ${title}` : title;
}

window.orderAlarm = {
    beep,
    chime,
    isBlocked,
    soundEnabled,
    unlock,
    toggleSound() {
        setSoundEnabled(!soundEnabled());
        unlock();
        document.dispatchEvent(new CustomEvent('order-alarm-changed'));
        return soundEnabled();
    },
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('orderAlarm', () => ({
        sound: soundEnabled(),
        blocked: isBlocked(),
        silenced: silenced(),

        init() {
            this.sync = () => this.refresh();
            document.addEventListener('order-alarm-changed', this.sync);
        },
        destroy() {
            document.removeEventListener('order-alarm-changed', this.sync);
        },
        refresh() {
            this.sound = soundEnabled();
            this.blocked = isBlocked();
            this.silenced = silenced();
        },
        update(detail) {
            update(detail);
            this.refresh();
        },
        toggleSound() {
            // Ligou com o som já liberado: toca uma vez para confirmar (se ainda estava
            // bloqueado, o desbloqueio já toca o alarme).
            if (window.orderAlarm.toggleSound() && !isBlocked()) chime();
            this.refresh();
        },
        unlockSound() {
            unlock().then(() => this.refresh());
        },
        silence() {
            state.silencedUpTo = state.newest;
            this.refresh();
        },
    }));
});
