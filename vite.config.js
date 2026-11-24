import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import os from 'os';

function getLocalIp() {
    if (process.env.VITE_HMR_HOST) {
        return process.env.VITE_HMR_HOST;
    }
    const interfaces = os.networkInterfaces();
    let fallback = null;
    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
            if (iface.family === 'IPv4' && !iface.internal) {
                if (
                    name.toLowerCase().includes('wi-fi') ||
                    name.toLowerCase().includes('wireless') ||
                    (name.toLowerCase().includes('ethernet') && !iface.address.startsWith('192.168.56.'))
                ) {
                    return iface.address;
                }
                if (!iface.address.startsWith('192.168.56.')) {
                    fallback = iface.address;
                }
            }
        }
    }
    return fallback || 'localhost';
}

const localIp = getLocalIp();

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        hmr: {
            host: localIp,
        },
    },
});
