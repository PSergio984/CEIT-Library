self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', event => {
    let data = {
        title: 'CEIT Library Notification',
        body: 'You have a new alert.',
        icon: '/images/ceit-logo.png',
        url: '/notifications'
    };

    if (event.data) {
        try {
            const payload = event.data.json();
            data = {
                title: payload.title || data.title,
                body: payload.body || data.body,
                icon: payload.icon || data.icon,
                url: payload.url || data.url
            };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.icon,
        data: {
            url: data.url
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    let targetUrl = event.notification.data && event.notification.data.url 
        ? event.notification.data.url 
        : '/notifications';
    
    // Ensure targetUrl is relative to our origin and avoid open redirects
    if (targetUrl.startsWith('/')) {
        targetUrl = self.location.origin + targetUrl;
    } else {
        const url = new URL(targetUrl, self.location.origin);
        if (url.origin !== self.location.origin) {
            targetUrl = self.location.origin + '/notifications';
        }
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            for (const client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
        })
    );
});

self.addEventListener('message', event => {
    // Verify the origin of the message
    if (event.origin !== self.location.origin) {
        return;
    }

    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
