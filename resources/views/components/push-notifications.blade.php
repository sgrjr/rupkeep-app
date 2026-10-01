@auth
@php
    // The key comes from VAPID_PUBLIC_KEY, not the source (TASK-467). With no
    // key configured there is nothing to subscribe to, so render nothing.
    $vapidPublicKey = trim((string) config('webpush.vapid.public_key'));
@endphp
@if($vapidPublicKey !== '')
<script>
(function () {
    const VAPID_PUBLIC_KEY = @json($vapidPublicKey);
    const SUBSCRIBE_URL = @json(url('/notifications/subscribe'), JSON_UNESCAPED_SLASHES);
    const UNSUBSCRIBE_URL = @json(url('/notifications/unsubscribe'), JSON_UNESCAPED_SLASHES);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    // Quiet on success (TASK-462); failures go to console.error.
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
    }

    async function sendSubscription(subscription) {
        // Always sent, even for an existing browser subscription (TASK-467):
        // the server row may have been dropped after a 410, and on a shared
        // phone the endpoint has to move to whoever is signed in now. The
        // server upserts by endpoint, so repeating it is harmless.
        await fetch(SUBSCRIBE_URL, {
            method: 'POST',
            body: JSON.stringify(subscription),
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }
        });
    }

    async function subscribeUser() {
        const registration = await navigator.serviceWorker.ready;

        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });
        }

        await sendSubscription(subscription);

        return subscription;
    }

    // Logging out on a shared device must stop this device receiving the
    // previous person's pushes (TASK-467): tell the server to forget the
    // endpoint and drop the browser subscription, then let the form go.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !/\/logout(\?|$)/.test(form.action || '')) {
            return;
        }

        navigator.serviceWorker.ready
            .then(reg => reg.pushManager.getSubscription())
            .then(sub => {
                if (!sub) return;
                fetch(UNSUBSCRIBE_URL, {
                    method: 'POST',
                    keepalive: true,
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }
                }).catch(() => {});
                return sub.unsubscribe();
            })
            .catch(() => {});
    }, true);

    navigator.serviceWorker.register('/sw.js')
        .then(() => subscribeUser())
        .catch(err => console.error('Push registration failed:', err));
})();
</script>
@endif
@endauth
