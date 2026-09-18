import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const root = fileURLToPath(new URL('../../', import.meta.url));

export async function browser() {
    const executable = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/google-chrome', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].filter(Boolean).find(existsSync);
    assert.ok(executable, 'Set CHROME_PATH to a Chrome/Chromium executable to run UI tests.');
    const profile = mkdtempSync(path.join(tmpdir(), 'growvia-ui-test-'));
    let html = '';
    const server = createServer((request, response) => {
        if (request.url === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            response.end(html);
        } else if (/^\/[a-z-]+\.js$/.test(request.url)) {
            const file = path.join(root, 'resources/js', request.url.slice(1));
            response.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
            response.end(existsSync(file) ? readFileSync(file) : '');
        } else {
            response.writeHead(404);
            response.end();
        }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = `http://127.0.0.1:${server.address().port}`;
    const chrome = spawn(executable, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
    let socket;
    let id = 0;
    const pending = new Map();
    const errors = [];
    const dispose = async () => {
        socket?.close();
        for (const job of pending.values()) { clearTimeout(job.timer); job.reject(Error('Browser disposed')); }
        pending.clear();
        chrome.kill();
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
        await delay(300);
        try { rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }); } catch {}
    };
    try {
        const endpoint = path.join(profile, 'DevToolsActivePort');
        for (let attempt = 0; !existsSync(endpoint) && attempt < 100; attempt++) await delay(100);
        assert.ok(existsSync(endpoint), 'Chrome did not expose a debugging port.');
        const port = readFileSync(endpoint, 'utf8').split('\n')[0].trim();
        const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
        socket.addEventListener('message', ({ data }) => {
            const message = JSON.parse(data);
            if (message.id) {
                const job = pending.get(message.id);
                if (!job) return;
                clearTimeout(job.timer);
                pending.delete(message.id);
                message.error ? job.reject(Error(JSON.stringify(message.error))) : job.resolve(message.result);
            } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text + ': ' + (message.params.exceptionDetails.exception?.description ?? ''));
        });
        const cdp = (method, params = {}) => new Promise((resolve, reject) => {
            const requestId = ++id;
            const timer = setTimeout(() => { pending.delete(requestId); reject(Error(`${method} timed out`)); }, 10000);
            pending.set(requestId, { resolve, reject, timer });
            socket.send(JSON.stringify({ id: requestId, method, params }));
        });
        const evaluate = async expression => {
            const result = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
            return result.result.value;
        };
        const settle = () => evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        await cdp('Page.enable');
        await cdp('Runtime.enable');
        await cdp('Emulation.setFocusEmulationEnabled', { enabled: true });
        await cdp('Emulation.setDeviceMetricsOverride', { width: 1000, height: 800, deviceScaleFactor: 1, mobile: false });
        await cdp('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
        return {
            cdp, evaluate, errors, dispose,
            async load(body, modules = ['custom-select.js']) {
                errors.length = 0;
                html = `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f6f3"><style>[hidden]{display:none!important}body{margin:24px;font:16px sans-serif}button,input,select{min-height:44px} .select-popover{position:fixed;z-index:100;background:white;border:1px solid #aaa;padding:6px;box-sizing:border-box}.select-option{min-height:44px}.select-option[data-active]{outline:2px solid green}.toast-region{position:fixed;bottom:20px;right:20px}.toast{background:white;border:1px solid #aaa;padding:12px}</style></head><body>${body}<script>window.submissions=[];window.addEventListener('submit',e=>{window.submissions.push({prevented:e.defaultPrevented,payload:Object.fromEntries(new FormData(e.target))});e.preventDefault()});</script>${modules.map(module => `<script type="module" src="/${module}"></script>`).join('')}</body></html>`;
                const loaded = new Promise((resolve, reject) => {
                    const timer = setTimeout(() => { socket.removeEventListener('message', listener); reject(Error('Navigation timed out')); }, 10000);
                    const listener = ({ data }) => { if (JSON.parse(data).method === 'Page.loadEventFired') { clearTimeout(timer); socket.removeEventListener('message', listener); resolve(); } };
                    socket.addEventListener('message', listener);
                });
                await cdp('Page.navigate', { url: origin + '/' });
                await loaded;
                await settle();
                assert.deepEqual(errors, []);
            },
            async click(selector) {
                const box = await evaluate(`(() => {const e=document.querySelector(${JSON.stringify(selector)});if(!e)throw Error('Missing '+${JSON.stringify(selector)});e.scrollIntoView({block:'center'});const r=e.getClientRects()[0];return {x:r.x+Math.min(20,r.width/2),y:r.y+r.height/2};})()`);
                await cdp('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, ...box });
                await cdp('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, ...box });
                await settle();
            },
            async key(key, modifiers = 0) {
                const codes = { Enter: 13, Escape: 27, Tab: 9, ArrowDown: 40, ArrowUp: 38, Home: 36, End: 35, ' ': 32 };
                const windowsVirtualKeyCode = codes[key] ?? key.toUpperCase().charCodeAt(0);
                await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key, windowsVirtualKeyCode, modifiers });
                await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key, windowsVirtualKeyCode, modifiers });
                await settle();
            },
        };
    } catch (error) {
        await dispose();
        throw error;
    }
}
