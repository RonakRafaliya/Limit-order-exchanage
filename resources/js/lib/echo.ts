import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<any>;
    }
}

window.Pusher = Pusher;

const getAuthToken = (): string | null => {
    return localStorage.getItem('auth_token');
};

const createEcho = () => {
    const token = getAuthToken();

    if (!token) {
        return null;
    }

    return new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY || 'your-app-key',
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
        forceTLS: true,
        authorizer: (channel: any) => {
            return {
                authorize: (socketId: string, callback: (error: Error | null, data: any) => void) => {
                    fetch('/broadcasting/auth', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            Authorization: `Bearer ${token}`,
                        },
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    })
                    .then((response) => response.json())
                    .then((data) => callback(null, data))
                    .catch((error: Error) => callback(error, null));
                },
            };
        },
    });
};

let echoInstance: Echo<any> | null = null;

export const getEcho = (): Echo<any> | null => {
    if (!echoInstance) {
        echoInstance = createEcho();
        if (echoInstance) {
            window.Echo = echoInstance;
        }
    }
    return echoInstance;
};

export default getEcho;
