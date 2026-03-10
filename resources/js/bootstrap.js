import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

export function initEcho(reverb) {
    if (!reverb?.key) {
        return;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host,
        wsPort: reverb.port ?? 80,
        wssPort: reverb.port ?? 443,
        forceTLS: (reverb.scheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
