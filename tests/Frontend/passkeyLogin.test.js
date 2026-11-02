import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createPasskeyLoginPoller } from '../../resources/js/passkeyLogin.js';

function setup(consume) {
    const states = [];
    const redirects = [];
    const failures = [];
    const timers = new Map();
    let timerId = 0;
    const poller = createPasskeyLoginPoller({
        consume,
        onState: state => states.push(state),
        onApproved: redirect => redirects.push(redirect),
        onFailure: failure => failures.push(failure),
        schedule: (callback, delay) => {
            const id = ++timerId;
            timers.set(id, { callback, delay });
            return id;
        },
        cancel: id => timers.delete(id),
    });

    async function next() {
        const [id, timer] = timers.entries().next().value;
        timers.delete(id);
        await timer.callback();
        return timer.delay;
    }

    return { poller, states, redirects, failures, timers, next };
}

test('a verified passkey immediately completes login with no password request', async () => {
    const flow = setup(async () => ({ status: 'approved', redirect: '/account' }));

    await flow.poller.start();

    assert.deepEqual(flow.states, ['waiting', 'approved']);
    assert.deepEqual(flow.redirects, ['/account']);
    assert.equal(flow.timers.size, 0);
    assert.deepEqual(flow.failures, []);
});

test('a pending request keeps waiting and redirects as soon as the phone approves', async () => {
    let approved = false;
    const flow = setup(async () => approved ? { status: 'approved', redirect: '/account' } : { status: 'pending' });
    await flow.poller.start();
    approved = true;

    assert.equal(await flow.next(), 2000);

    assert.deepEqual(flow.redirects, ['/account']);
    assert.equal(flow.states.at(-1), 'approved');
});

for (const status of [undefined, 408, 429, 503]) {
    test(`a temporary ${status || 'network'} error retries rather than returning to password login`, async () => {
        let requests = 0;
        const flow = setup(async () => {
            if (++requests === 1) throw { response: status ? { status } : undefined };
            return { status: 'approved', redirect: '/account' };
        });

        await flow.poller.start();
        assert.equal(flow.states.at(-1), 'retrying');
        await flow.next();

        assert.deepEqual(flow.redirects, ['/account']);
        assert.deepEqual(flow.failures, []);
    });
}

test('rate limiting respects Retry-After before completing login', async () => {
    let requests = 0;
    const flow = setup(async () => {
        if (++requests === 1) throw { response: { status: 429, headers: { 'retry-after': '45' } } };
        return { status: 'approved', redirect: '/account' };
    });
    await flow.poller.start();

    assert.equal(await flow.next(), 45000);
    assert.deepEqual(flow.redirects, ['/account']);
});

test('returning to the browser cannot bypass a rate-limit cooldown', async () => {
    let requests = 0;
    const flow = setup(async () => {
        if (++requests === 1) throw { response: { status: 429, headers: { 'retry-after': '45' } } };
        return { status: 'approved', redirect: '/account' };
    });
    await flow.poller.start();
    await flow.poller.check();
    await flow.poller.check();

    assert.equal(requests, 1);
    assert.equal(await flow.next(), 45000);
    assert.deepEqual(flow.redirects, ['/account']);
});

test('repeated network failures leave an explicit retryable QR error, never password mode', async () => {
    let online = false;
    const flow = setup(async () => {
        if (!online) throw new Error('Offline');
        return { status: 'approved', redirect: '/account' };
    });
    await flow.poller.start();
    for (let attempt = 0; attempt < 5; attempt++) await flow.next();

    assert.equal(flow.states.at(-1), 'failed');
    assert.equal(flow.failures[0].recoverable, true);
    assert.equal(flow.states.includes('idle'), false);
    assert.equal(flow.timers.size, 0);

    online = true;
    await flow.poller.start();
    assert.deepEqual(flow.redirects, ['/account']);
});

for (const status of [401, 403, 410, 419]) {
    test(`a terminal ${status} error clearly fails without switching to password login`, async () => {
        const flow = setup(async () => { throw { response: { status } }; });

        await flow.poller.start();

        assert.equal(flow.states.at(-1), 'failed');
        assert.equal(flow.failures[0].recoverable, false);
        assert.equal(flow.states.includes('idle'), false);
        assert.equal(flow.timers.size, 0);
        assert.deepEqual(flow.redirects, []);
    });
}

test('stopping ignores the result of an already-running status request', async () => {
    let resolve;
    const flow = setup(() => new Promise(done => { resolve = done; }));
    const pending = flow.poller.start();
    flow.poller.stop();
    resolve({ status: 'approved', redirect: '/account' });

    await pending;

    assert.deepEqual(flow.redirects, []);
    assert.equal(flow.timers.size, 0);
});

test('focus checks cannot start overlapping status requests', async () => {
    let requests = 0;
    let resolve;
    const flow = setup(() => {
        requests++;
        return new Promise(done => { resolve = done; });
    });
    const pending = flow.poller.start();
    await flow.poller.check();
    await flow.poller.check();
    resolve({ status: 'approved', redirect: '/account' });
    await pending;

    assert.equal(requests, 1);
    assert.deepEqual(flow.redirects, ['/account']);
});

test('a new attempt ignores a stale response from a previous attempt', async () => {
    const responses = [];
    const flow = setup(() => new Promise(resolve => responses.push(resolve)));
    const old = flow.poller.start();
    const current = flow.poller.start();
    responses[0]({ status: 'approved', redirect: '/wrong-account' });
    responses[1]({ status: 'approved', redirect: '/account' });

    await Promise.all([old, current]);

    assert.deepEqual(flow.redirects, ['/account']);
});

test('an unexpected response cannot silently strand the QR login', async () => {
    const flow = setup(async () => ({ status: 'approved' }));

    await flow.poller.start();

    assert.equal(flow.states.at(-1), 'failed');
    assert.equal(flow.failures[0].recoverable, false);
});
