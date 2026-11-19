import { spawn } from 'child_process';
import { existsSync, unlinkSync } from 'fs';

// 1. Clean up any stale hot file on startup
try {
    if (existsSync('public/hot')) {
        unlinkSync('public/hot');
    }
} catch (e) {}

// 2. Determine PHP binary (checks PATH, falls back to XAMPP PHP on Windows)
let phpBin = 'php';
if (process.platform === 'win32') {
    const xamppPhp = 'C:\\xampp\\php\\php.exe';
    if (existsSync(xamppPhp)) {
        phpBin = xamppPhp;
    }
}

console.log('\x1b[36m%s\x1b[0m', '✨ Starting Aura Luxe Development Environment...');

// 3. Start Laravel Backend Server on port 8000
const phpProcess = spawn(phpBin, ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], {
    shell: true,
    stdio: ['inherit', 'pipe', 'pipe'],
});

phpProcess.stdout.on('data', (data) => {
    const str = data.toString();
    if (str.includes('Server running on')) {
        console.log('\x1b[32m%s\x1b[0m', '➜  Application URL:   http://127.0.0.1:8000');
    } else if (!str.includes('Press Ctrl+C') && str.trim()) {
        process.stdout.write(`\x1b[34m[laravel]\x1b[0m ${str}`);
    }
});

phpProcess.stderr.on('data', (data) => {
    process.stderr.write(`\x1b[31m[laravel:err]\x1b[0m ${data}`);
});

// 4. Start Vite Frontend HMR Server
const viteProcess = spawn('npx', ['vite'], {
    shell: true,
    stdio: ['inherit', 'pipe', 'pipe'],
});

viteProcess.stdout.on('data', (data) => {
    const str = data.toString();
    const localMatch = str.match(/Local:\s+(http:\/\/[^\s]+)/);
    if (localMatch) {
        console.log('\x1b[35m%s\x1b[0m', `➜  Vite Asset HMR:    ${localMatch[1]}`);
        console.log('\x1b[90m%s\x1b[0m', '   Ready! Open http://127.0.0.1:8000 in your browser. (Ctrl+C to stop)\n');
    } else if (str.trim() && !str.includes('LARAVEL v') && !str.includes('APP_URL:')) {
        process.stdout.write(`\x1b[35m[vite]\x1b[0m ${str}`);
    }
});

viteProcess.stderr.on('data', (data) => {
    process.stderr.write(`\x1b[31m[vite:err]\x1b[0m ${data}`);
});

function cleanup() {
    try {
        if (existsSync('public/hot')) {
            unlinkSync('public/hot');
        }
    } catch (e) {}

    try {
        phpProcess.kill('SIGINT');
    } catch (e) {}
    try {
        viteProcess.kill('SIGINT');
    } catch (e) {}
    process.exit(0);
}

process.on('SIGINT', cleanup);
process.on('SIGTERM', cleanup);
process.on('exit', cleanup);
