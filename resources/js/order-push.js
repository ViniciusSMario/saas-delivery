/*
 * Página do pedido no cardápio público: "Avisar no meu celular" (Web Push, sem conta) e
 * atualização ao vivo. O service worker é o mesmo do painel (/sw.js), sem cache.
 */
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

const registration = pushSupported ? navigator.serviceWorker.register('/sw.js').catch(() => null) : Promise.resolve(null);

function keyToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

const store = {
    get: (key) => { try { return localStorage.getItem(key); } catch (e) { return null; } },
    set: (key, value) => { try { value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value); } catch (e) { /* sem armazenamento */ } },
};

function send(url, method, body) {
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': meta('csrf-token') },
        body: JSON.stringify(body),
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('orderTracking', ({ url, statusUrl, token, orderId, status, final }) => ({
        status,
        pushSupported,
        needsInstall: isIos && !isStandalone,
        permission: pushSupported ? Notification.permission : 'unsupported',
        subscribed: false,
        busy: false,
        error: '',
        key: `order-push:${token}`,

        async init() {
            if (pushSupported && this.permission === 'granted' && store.get(this.key)) {
                const reg = await registration;
                this.subscribed = Boolean(await reg?.pushManager.getSubscription());
            }

            this.clearNotifications();
            if (final) return;

            // Enquanto o pedido anda: confere a cada 10 s e ao voltar para a aba; recarrega quando muda.
            const check = async () => {
                try {
                    const data = await (await fetch(statusUrl, { headers: { Accept: 'application/json' } })).json();
                    if (data.status !== this.status) location.reload();
                } catch (e) { /* sem internet: tenta de novo */ }
            };
            setInterval(check, 10000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    check();
                    this.clearNotifications();
                }
            });
        },

        // Abriu a página: o aviso deste pedido sai da bandeja (a tela já mostra a situação).
        clearNotifications() {
            registration.then((reg) => reg?.getNotifications?.({ tag: `acompanhar-${orderId}` }).then((list) => list.forEach((n) => n.close()))).catch(() => {});
        },

        async enable() {
            this.busy = true;
            this.error = '';
            try {
                this.permission = await Notification.requestPermission();
                if (this.permission !== 'granted') {
                    if (this.permission === 'denied') this.error = 'As notificações estão bloqueadas neste navegador.';
                    return;
                }
                const reg = await navigator.serviceWorker.ready;
                const subscription = (await reg.pushManager.getSubscription())
                    || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(meta('push-key')) }));
                const response = await send(`${url}/avisos`, 'POST', subscription.toJSON());
                if (!response.ok) throw new Error('falhou');
                store.set(this.key, '1');
                this.subscribed = true;
            } catch (e) {
                this.error = 'Não foi possível ativar agora. Tente de novo.';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;
            try {
                const reg = await registration;
                const subscription = await reg?.pushManager.getSubscription();
                // Só desliga este pedido: a inscrição do navegador pode servir a outros pedidos.
                if (subscription) await send(`${url}/avisos`, 'DELETE', { endpoint: subscription.endpoint });
                store.set(this.key, null);
                this.subscribed = false;
            } finally {
                this.busy = false;
            }
        },
    }));
});
