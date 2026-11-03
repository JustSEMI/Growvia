import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const css = readFileSync(new URL('resources/css/app.css', root), 'utf8');

test('page content fades in on load and cross-page transitions share the easing', () => {
    assert.ok(existsSync(new URL('resources/css/app.css', root)));
    assert.match(css, /@keyframes page-in/);
    assert.match(css, /@view-transition/);
    assert.match(css, /\.page-content[^{]*\{[^}]*animation:\s*page-in/);
    assert.match(css, /::view-transition-old\(root\)/);
});
