
<script>

console.log('started');
const VAPID_PUBLIC_KEY = "BMPgW_eNDtZPVH-RYHfAPEkzR6Fvmw7A247WEuFrYH82OLXV7nK6zTRIT_F1zd-0vUfg51P-pGwVmeQugeHjsiA";

    async function subscribeUser() {
        // 1. Wait for the service worker to be fully active
        const registration = await navigator.serviceWorker.ready;
        
        console.log('Service Worker is ready and active.');

        // 2. Check for existing subscription
        const existingSubscription = await registration.pushManager.getSubscription();
        if (existingSubscription) {
            console.log('User already subscribed.');
            return existingSubscription;
        }

        // 3. Request permission and subscribe
        try {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            // 4. Send to Laravel
            await fetch('/notifications/subscribe', {
                method: 'POST',
                body: JSON.stringify(subscription),
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            console.log('Successfully sent subscription to server!');
        } catch (error) {
            console.error('Push subscription failed:', error);
        }
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