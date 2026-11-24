import { spawn } from 'child_process';
import { existsSync, unlinkSync } from 'fs';
import os from 'os';

// 1. Clean up any stale hot file on startup
try {
    if (existsSync('public/hot')) {
        unlinkSync('public/hot');
    }
} catch (e) {}

// 2. Dynamically determine network IP
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
    return fallback || '127.0.0.1';
}

const networkIp = getLocalIp();
process.env.VITE_HMR_HOST = networkIp;

// 3. Determine PHP binary (checks PATH, falls back to XAMPP PHP on Windows)
let phpBin = 'php';
if (process.platform === 'win32') {
    const xamppPhp = 'C:\\xampp\\php\\php.exe';
    if (existsSync(xamppPhp)) {
        phpBin = xamppPhp;
    }
}

console.log('\x1b[36m%s\x1b[0m', '✨ Starting Aura Luxe Development Environment...');

// 4. Start Laravel Backend Server on port 8000 listening on all interfaces (0.0.0.0)
const phpProcess = spawn(phpBin, ['artisan', 'serve', '--host=0.0.0.0', '--port=8000'], {
    shell: true,
    stdio: ['inherit', 'pipe', 'pipe'],
    env: { ...process.env, VITE_HMR_HOST: networkIp },
});

phpProcess.stdout.on('data', (data) => {
    const str = data.toString();
    if (str.includes('Server running on')) {
        console.log('\x1b[32m%s\x1b[0m', '➜  Local App:    http://127.0.0.1:8000');
        console.log('\x1b[32m%s\x1b[0m', `➜  Network App:  http://${networkIp}:8000`);
    } else if (!str.includes('Press Ctrl+C') && str.trim()) {
        process.stdout.write(`\x1b[34m[laravel]\x1b[0m ${str}`);
    }
});

phpProcess.stderr.on('data', (data) => {
    process.stderr.write(`\x1b[31m[laravel:err]\x1b[0m ${data}`);
});

// 5. Start Vite Frontend HMR Server on host 0.0.0.0
const viteProcess = spawn('npx', ['vite'], {
    shell: true,
    stdio: ['inherit', 'pipe', 'pipe'],
    env: { ...process.env, VITE_HMR_HOST: networkIp },
});

viteProcess.stdout.on('data', (data) => {
    const str = data.toString();
    const localMatch = str.match(/Local:\s+http:\/\/[^:]+:(\d+)/i);
    if (localMatch) {
        const vitePort = localMatch[1];
        console.log('\x1b[35m%s\x1b[0m', `➜  Vite Asset HMR:    http://${networkIp}:${vitePort}`);
        console.log('\x1b[90m%s\x1b[0m', `   Ready! Open http://127.0.0.1:8000 (Local) or http://${networkIp}:8000 (Network) in your browser. (Ctrl+C to stop)\n`);
    } else if (str.trim() && !str.includes('LARAVEL v') && !str.includes('APP_URL:')) {
        process.stdout.write(`\x1b[35m[vite]\x1b[0m ${str}`);
    }
});

viteProcess.stderr.on('data', (data) => {
    process.stderr.write(`\x1b[31m[vite:err]\x1b[0m ${data}`);
});

function killProcessTree(pid) {
    if (!pid) return;
    try {
        if (process.platform === 'win32') {
            spawn('taskkill', ['/pid', pid.toString(), '/T', '/F']);
        } else {
            phpProcess.kill('SIGINT');
        }
    } catch (e) {}
}

function cleanup() {
    try {
        if (existsSync('public/hot')) {
            unlinkSync('public/hot');
        }
    } catch (e) {}

    killProcessTree(phpProcess?.pid);
    killProcessTree(viteProcess?.pid);
    process.exit(0);
}

process.on('SIGINT', cleanup);
process.on('SIGTERM', cleanup);
process.on('exit', cleanup);
