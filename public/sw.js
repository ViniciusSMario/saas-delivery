/*
 * Service worker do app do restaurante (o mesmo arquivo serve todos os domínios).
 * Só cuida das notificações: não guarda páginas em cache (o painel é sempre ao vivo).
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Aviso chegou: mostra a notificação. A mesma "tag" substitui o aviso anterior do mesmo pedido.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'Novo aviso', {
        body: data.body || '',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        // Aviso do cliente traz o logo pelo endereço do cardápio (funciona também em /cardapio/{slug}).
        icon: data.icon || '/app-icon/192.png',
        badge: '/app-icon/badge.png',
        vibrate: data.sticky ? [300, 120, 300, 120, 300] : [200, 100, 200],
        // Pedido novo: no computador, fica na tela até alguém clicar.
        requireInteraction: Boolean(data.sticky),
        timestamp: data.sent_at || Date.now(),
        data: { url: data.url || '/' },
    }));
});

// Toque na notificação: abre (ou traz para a frente) o app na tela do pedido.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (new URL(client.url).origin === self.location.origin) {
                await client.focus();
                try {
                    await client.navigate(url);
                } catch (e) {
                    // Janela não controlada por este service worker: abre uma nova.
                    await self.clients.openWindow(url);
                }
                return;
            }
        }

        await self.clients.openWindow(url);
    })());
});
