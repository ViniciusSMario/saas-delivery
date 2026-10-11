/*
 * App instalável e notificações no celular (painel do restaurante).
 * - registra o service worker (/sw.js);
 * - guarda o convite de instalação do Android/Chrome para o botão "Instalar app";
 * - liga/desliga as notificações deste celular e mantém a inscrição ligada ao usuário logado;
 * - ao abrir o app, limpa as notificações já vistas (a tela mostra a situação atual).
 */
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';

const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

let installPrompt = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    document.dispatchEvent(new CustomEvent('app-installable'));
});
window.addEventListener('appinstalled', () => {
    installPrompt = null;
    document.dispatchEvent(new CustomEvent('app-installed'));
});

const registration = 'serviceWorker' in navigator
    ? navigator.serviceWorker.register('/sw.js').catch(() => null)
    : Promise.resolve(null);

function keyToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

function send(url, method, body) {
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': meta('csrf-token') },
        body: body ? JSON.stringify(body) : undefined,
    });
}

async function currentSubscription() {
    const reg = await registration;
    return reg ? reg.pushManager.getSubscription() : null;
}

async function save(subscription) {
    const response = await send('/notificacoes/assinatura', 'POST', subscription.toJSON());
    if (!response.ok) throw new Error('Não foi possível salvar a inscrição.');
}

const appPush = {
    async enable() {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return permission;

        const reg = await navigator.serviceWorker.ready;
        const subscription = (await reg.pushManager.getSubscription())
            || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(meta('push-key')) }));
        await save(subscription);

        return 'granted';
    },
    async disable() {
        const subscription = await currentSubscription();
        if (!subscription) return;
        await send('/notificacoes/assinatura', 'DELETE', { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
    },
    async test() {
        const response = await send('/notificacoes/teste', 'POST');
        return response.ok;
    },
};
window.appPush = appPush;

// Celular já autorizado: liga a inscrição ao usuário logado (uma vez por sessão do navegador).
(async () => {
    const user = meta('push-user');
    if (!user || !pushSupported || Notification.permission !== 'granted') return;

    const flag = `push-sync:${user}`;
    try {
        if (sessionStorage.getItem(flag)) return;
    } catch (e) { /* armazenamento bloqueado: sincroniza de novo */ }

    const subscription = await currentSubscription();
    if (!subscription) return;

    try {
        await save(subscription);
        sessionStorage.setItem(flag, '1');
    } catch (e) { /* tenta na próxima página */ }
})();

// Ao abrir ou voltar para o app, as notificações dele saem da bandeja.
function clearNotifications() {
    registration.then((reg) => reg?.getNotifications?.().then((list) => list.forEach((n) => n.close()))).catch(() => {});
}
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') clearNotifications();
});
clearNotifications();

// Cartão "App no celular" (Minha conta) e o aviso no topo do modo app.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('appDevice', () => ({
        isIos,
        standalone: isStandalone(),
        canInstall: Boolean(installPrompt),
        installed: isStandalone(),
        pushSupported,
        permission: pushSupported ? Notification.permission : 'unsupported',
        subscribed: false,
        ready: false,
        busy: false,
        message: '',
        error: '',
        dismissed: false,

        async init() {
            document.addEventListener('app-installable', () => { this.canInstall = true; });
            document.addEventListener('app-installed', () => { this.canInstall = false; this.installed = true; });
            try { this.dismissed = localStorage.getItem('push-banner-dismissed') === '1'; } catch (e) { /* sem armazenamento */ }
            this.subscribed = this.permission === 'granted' && Boolean(await currentSubscription());
            this.ready = true;
        },

        // iPhone só recebe notificação com o app na Tela de Início.
        get needsInstallForPush() { return this.isIos && !this.standalone; },
        // Aviso no topo do modo app: "install" (iPhone fora da Tela de Início), "enable" (ainda não ativou) ou nada.
        get bannerMode() {
            if (!this.ready || this.dismissed) return null;
            if (this.needsInstallForPush) return 'install';
            if (this.pushSupported && !this.subscribed && this.permission !== 'denied') return 'enable';
            return null;
        },

        async install() {
            if (!installPrompt) return;
            installPrompt.prompt();
            const { outcome } = await installPrompt.userChoice;
            installPrompt = null;
            this.canInstall = false;
            if (outcome === 'accepted') this.installed = true;
        },

        async enable() {
            this.busy = true;
            this.message = '';
            this.error = '';
            try {
                this.permission = await appPush.enable();
                this.subscribed = this.permission === 'granted';
                if (this.subscribed) this.message = 'Notificações ativadas neste celular.';
                else if (this.permission === 'denied') this.error = 'Você bloqueou as notificações. Libere nas configurações do navegador ou do celular.';
            } catch (e) {
                this.error = 'Não foi possível ativar agora. Tente de novo.';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;
            this.message = '';
            try {
                await appPush.disable();
                this.subscribed = false;
                this.message = 'Notificações desativadas neste celular.';
            } finally {
                this.busy = false;
            }
        },

        async test() {
            this.busy = true;
            this.message = '';
            this.error = '';
            const ok = await appPush.test();
            this.busy = false;
            if (ok) this.message = 'Aviso de teste enviado. Ele chega em alguns segundos.';
            else this.error = 'Não foi possível enviar o teste.';
        },

        dismiss() {
            this.dismissed = true;
            try { localStorage.setItem('push-banner-dismissed', '1'); } catch (e) { /* sem armazenamento */ }
        },
    }));
});
