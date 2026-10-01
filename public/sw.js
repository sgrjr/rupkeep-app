// Service worker for web push (TASK-467).
//
// The payload is built by App\Notifications\JobUpdate through WebPushMessage,
// which puts the link under data.data.url. The old worker read data.url and
// so opened `undefined` on every tap.

self.addEventListener('push', function (event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'Update', body: event.data ? event.data.text() : '' };
    }

    const url = (payload.data && payload.data.url) || payload.url || '/';

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Update', {
            body: payload.body || '',
            icon: payload.icon || '/favicon.ico',
            badge: payload.badge || '/favicon.ico',
            data: { url: url },
            actions: Array.isArray(payload.actions) ? payload.actions : []
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            for (const client of windows) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
