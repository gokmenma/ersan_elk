/**
 * Mobile Admin Push Notification Configuration
 */
const PushConfig = {
    vapidPublicKey: 'BGSV9o3jOcpLBMINUUEuw6Nesv7cj3wGjjlzQQcZ9b4qkSr6sQlNF7np44jlMNuqMuYKicmVrJK05yIPXx4lGP0', // Same as PWA

    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push notifications are not supported in this browser.');
            return;
        }

        try {
            // Register Service Worker from root
            const registration = await navigator.serviceWorker.register('../sw.js', { scope: '../' });
            console.log('Service Worker registered for Mobile Admin:', registration);

            // Check current subscription
            const subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                console.log('Mobile Admin: User is already subscribed');
                this.saveSubscription(subscription);
            } else {
                this.askPermission();
            }
        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    },

    async askPermission() {
        if (Notification.permission === 'granted') {
            this.subscribe();
        } else if (Notification.permission !== 'denied') {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.subscribe();
            }
        }
    },

    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            const applicationServerKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });

            console.log('User subscribed:', subscription);
            this.saveSubscription(subscription);
            
            if (typeof Alert !== 'undefined') {
                Alert.success('Bildirimler Aktif', 'Görev vakti geldiğinde size bildirim göndereceğiz.');
            }
        } catch (error) {
            console.error('Failed to subscribe user:', error);
        }
    },

    async saveSubscription(subscription) {
        try {
            await $.ajax({
                url: '../views/gorevler/api.php',
                type: 'POST',
                data: {
                    action: 'save-subscription',
                    subscription: JSON.stringify(subscription)
                }
            });
            console.log('Subscription saved to backend');
        } catch (error) {
            console.error('Error saving subscription:', error);
        }
    },

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
};

// Initialize on page load
$(document).ready(() => {
    PushConfig.init();
});
