
<script>

console.log('started');
const VAPID_PUBLIC_KEY = "BMPgW_eNDtZPVH-RYHfAPEkzR6Fvmw7A247WEuFrYH82OLXV7nK6zTRIT_F1zd-0vUfg51P-pGwVmeQugeHjsiA";

// 1. Register the Service Worker
async function registerServiceWorker() {
    return navigator.serviceWorker.register('/sw.js');
}

// 2. Ask for Permission & Subscribe
async function subscribeUser() {
    const registration = await registerServiceWorker();
    
    // Check if user already has a subscription
    const existingSubscription = await registration.pushManager.getSubscription();
    if (existingSubscription) return existingSubscription;

    // Request permission and create new subscription
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
    });

    // 3. Send subscription to Laravel
    await fetch('/notifications/subscribe', {
        method: 'POST',
        body: JSON.stringify(subscription),
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });
}

    // Helper to convert VAPID key format
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
    }

    // Trigger the registration and subscription process immediately on load
    subscribeUser()
        .then(sub => console.log('Successfully subscribed:', sub))
        .catch(err => console.error('Subscription failed:', err));

</script>