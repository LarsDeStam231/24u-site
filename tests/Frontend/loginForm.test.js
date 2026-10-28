import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { parse, compileTemplate } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const filename = new URL('../../resources/js/components/ui/LoginForm.vue', import.meta.url);
const { descriptor } = parse(await readFile(filename, 'utf8'));
const compiled = compileTemplate({ source: descriptor.template.content, filename: filename.pathname, id: 'login-form-test', ssr: true, ssrCssVars: [] });
assert.deepEqual(compiled.errors, []);
const code = compiled.code
    .replaceAll('"vue/server-renderer"', JSON.stringify(import.meta.resolve('vue/server-renderer')))
    .replaceAll('"vue"', JSON.stringify(import.meta.resolve('vue')));
const { ssrRender } = await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`);

async function render(qrState) {
    const noop = () => {};
    return renderToString(createSSRApp({
        components: {
            ActionButton: { setup: (_, { slots }) => () => h('button', slots.default?.()) },
        },
        ssrRender,
        setup: () => ({
            qrState, email: 'user@example.com', password: '', inputClass: '', busy: false,
            error: '', message: '', canRetry: qrState === 'failed',
            options: { hasPassword: true, hasPasskeys: true }, links: { registration: '/registreren' },
            pairing: { qr: { image: 'data:image/svg+xml;base64,example' }, code: '123456' },
            identify: noop, login: noop, changeEmail: noop, retryCompletion: noop,
            createPairing: noop, usePassword: noop, requestMail: noop,
        }),
    }));
}

test('password login remains available before selecting QR login', async () => {
    const html = await render('idle');

    assert.match(html, /type="password"/);
    assert.match(html, /Inloggen met wachtwoord/);
});

for (const state of ['waiting', 'retrying', 'failed', 'approved']) {
    test(`the ${state} QR screen never asks for a password`, async () => {
        const html = await render(state);

        assert.doesNotMatch(html, /type="password"/);
        assert.doesNotMatch(html, /Inloggen met wachtwoord/);
        assert.match(html, /Inloggen met je passkey/);
    });
}

test('a successful QR login shows completion instead of asking for another factor', async () => {
    const html = await render('approved');

    assert.match(html, /Je bent ingelogd en wordt doorgestuurd/);
    assert.doesNotMatch(html, /Annuleren en toch mijn wachtwoord gebruiken/);
    assert.doesNotMatch(html, /type="password"/);
});

test('a failed completion offers a retry without making password entry mandatory', async () => {
    const html = await render('failed');

    assert.match(html, /Afronden opnieuw proberen/);
    assert.match(html, /Nieuwe QR-code maken/);
    assert.doesNotMatch(html, /type="password"/);
});
