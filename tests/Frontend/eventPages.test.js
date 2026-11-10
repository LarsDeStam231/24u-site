import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { compileScript, parse } from '@vue/compiler-sfc';
import { createRenderer, createSSRApp, h, nextTick } from 'vue';
import { renderToString } from 'vue/server-renderer';
import axios from 'axios';

const modules = new Map();
const cleanups = new WeakMap();

function after(t, cleanup) {
    if (!cleanups.has(t)) {
        const callbacks = [];
        cleanups.set(t, callbacks);
        t.after(() => { while (callbacks.length) callbacks.pop()(); });
    }
    cleanups.get(t).push(cleanup);
}

async function loadComponent(relativePath) {
    const filename = new URL(`../../resources/js/${relativePath}`, import.meta.url);
    return (await import(await compileComponent(filename))).default;
}

async function compileComponent(filename) {
    if (modules.has(filename.href)) return modules.get(filename.href);

    const { descriptor } = parse(await readFile(filename, 'utf8'));
    const compiled = compileScript(descriptor, { id: filename.pathname, inlineTemplate: true });
    let code = compiled.content;

    for (const match of [...code.matchAll(/from (['"])([^'"]+)\1/g)]) {
        const specifier = match[2];
        let resolved;
        if (specifier.startsWith('.')) {
            const dependency = new URL(specifier.endsWith('.vue') ? specifier : `${specifier}.js`, filename);
            resolved = specifier.endsWith('.vue') ? await compileComponent(dependency) : dependency.href;
        } else {
            resolved = import.meta.resolve(specifier);
        }
        code = code.replace(match[0], `from ${JSON.stringify(resolved)}`);
    }

    const url = `data:text/javascript;base64,${Buffer.from(`${code}\n//# sourceURL=${filename.href}`).toString('base64')}`;
    modules.set(filename.href, url);
    return url;
}

const DonationCounter = await loadComponent('components/home/DonationCounter.vue');
const DonationCard = await loadComponent('components/home/DonationCard.vue');
const ChallengesPage = await loadComponent('pages/ChallengesPage.vue');
const ProgramPage = await loadComponent('pages/ProgramPage.vue');
const ProgramCard = await loadComponent('components/home/ProgramCard.vue');
const CountdownTimer = await loadComponent('components/home/CountdownTimer.vue');
const ContentManagement = await loadComponent('components/ContentManagement.vue');
const QrCards = await loadComponent('components/ui/QrCards.vue');
const AccountDashboard = await loadComponent('components/account/AccountDashboard.vue');
const AccountQrCodes = await loadComponent('components/account/AccountQrCodes.vue');
const SectionTabs = await loadComponent('components/ui/SectionTabs.vue');
const PersonalFlyer = await loadComponent('components/account/PersonalFlyer.vue');

function node(type, text = '') {
    return {
        type, text, props: {}, children: [], parent: null, value: '', style: {},
        addEventListener() {},
        getRootNode() { return this; },
        get options() { return this.children.filter(child => child.type === 'option'); },
    };
}

function remove(child) {
    if (child.parent) {
        child.parent.children.splice(child.parent.children.indexOf(child), 1);
        child.parent = null;
    }
}

const renderer = createRenderer({
    createElement: type => node(type),
    createText: text => node('#text', text),
    createComment: text => node('#comment', text),
    setText: (element, text) => { element.text = text; },
    setElementText: (element, text) => { element.text = text; element.children = []; },
    patchProp: (element, key, previous, value) => {
        element.props[key] = value;
        if (key === 'value') { element.value = value; element._value = value; }
    },
    insert: (child, parent, anchor = null) => {
        remove(child);
        parent.children.splice(anchor ? parent.children.indexOf(anchor) : parent.children.length, 0, child);
        child.parent = parent;
    },
    remove,
    parentNode: element => element.parent,
    nextSibling: element => element.parent?.children[element.parent.children.indexOf(element) + 1] ?? null,
});

function mount(t, component, props) {
    const root = node('root');
    const app = renderer.createApp({ render: () => h(component, props) });
    app.mount(root);
    let mounted = true;
    const unmount = () => {
        if (mounted) app.unmount();
        mounted = false;
    };
    after(t, unmount);
    return { root, unmount };
}

function descendants(root, predicate) {
    return [root, ...root.children.flatMap(child => descendants(child, () => true))].filter(predicate);
}

function withClass(root, className) {
    return descendants(root, element => (element.props.class || '').split(' ').includes(className));
}

function text(root) {
    return root.type === '#comment' ? '' : root.text + root.children.map(text).join('');
}

function browser(t, respond) {
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;
    const previousFetch = globalThis.fetch;
    const previousDocumentClass = globalThis.Document;
    const previousShadowRoot = globalThis.ShadowRoot;
    const timers = new Map();
    const requests = [];
    let timerId = 0;
    globalThis.window = {
        setInterval: (callback, delay) => { timers.set(++timerId, { callback, delay }); return timerId; },
        clearInterval: id => timers.delete(id),
        confirm: () => true,
    };
    globalThis.document = { querySelector: () => ({ content: 'csrf-for-test' }) };
    globalThis.Document = class {};
    globalThis.ShadowRoot = class {};
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return respond(url, options);
    };
    after(t, () => {
        globalThis.window = previousWindow;
        globalThis.document = previousDocument;
        globalThis.fetch = previousFetch;
        globalThis.Document = previousDocumentClass;
        globalThis.ShadowRoot = previousShadowRoot;
    });
    return {
        timers, requests,
        refresh: async () => { await [...timers.values()][0].callback(); await nextTick(); },
    };
}

const response = raisedCents => ({ ok: true, json: async () => ({ raisedCents }) });
const fundraising = { raisedCents: 0, goalCents: 1000000 };
const challenges = [
    { amountCents: 5000, name: 'Eerste challenge', description: 'Eerste opdracht.' },
    { amountCents: 10000, name: 'Tweede challenge', description: 'Tweede opdracht.' },
];
const counterProps = { fundraising, challenges, endpoint: '/donations/total' };

async function flushUpdates() {
    await Promise.resolve();
    await Promise.resolve();
    await nextTick();
}

test('the counter polls every ten seconds and keeps the last known total after a failed refresh', async (t) => {
    let offline = false;
    const flow = browser(t, () => {
        if (offline) throw new Error('Offline');
        return response(1250);
    });
    const updatedTotals = [];
    const { root } = mount(t, DonationCounter, { ...counterProps, onRaisedCentsUpdated: amount => updatedTotals.push(amount) });
    await flushUpdates();

    assert.match(text(root), /12,50/);
    assert.doesNotMatch(text(root), /Voorbeeld/);
    assert.deepEqual(updatedTotals, [1250]);
    assert.equal([...flow.timers.values()][0].delay, 10000);
    assert.equal(flow.requests[0].options.cache, 'no-store');

    offline = true;
    await flow.refresh();

    assert.match(text(root), /12,50/);
    assert.deepEqual(updatedTotals, [1250]);
});

test('unmounting stops polling and ignores the result of an outstanding refresh', async (t) => {
    let finish;
    const flow = browser(t, () => new Promise(resolve => { finish = resolve; }));
    const updatedTotals = [];
    const { unmount } = mount(t, DonationCounter, { ...counterProps, onRaisedCentsUpdated: amount => updatedTotals.push(amount) });

    unmount();
    finish(response(1000));
    await flushUpdates();

    assert.equal(flow.timers.size, 0);
    assert.deepEqual(updatedTotals, []);
});

test('the counter does not start overlapping requests while a refresh is pending', async (t) => {
    let finishRefresh;
    const flow = browser(t, () => new Promise(resolve => { finishRefresh = resolve; }));
    const { root } = mount(t, DonationCounter, counterProps);

    await flow.refresh();

    assert.equal(flow.requests.length, 1);
    finishRefresh(response(1000));
    await flushUpdates();

    assert.match(text(withClass(root, 'counter-total')[0]), /10/);
});

test('obsolete preview flags cannot show example labels or test donation controls', async (t) => {
    const flow = browser(t, () => response(0));
    const { root } = mount(t, DonationCounter, {
        ...counterProps, fundraising: { ...fundraising, isPreview: true, canManagePreview: true },
    });
    await flushUpdates();

    assert.doesNotMatch(text(root), /Test:|Reset teller|Voorbeeld/);
    assert.equal(descendants(root, element => element.type === 'button').length, 0);
    assert.ok(flow.requests.every(request => request.url === counterProps.endpoint && !request.options.method));
});

test('a rejected refresh preserves the real total and recovers on the next refresh', async (t) => {
    let rejected = true;
    const flow = browser(t, () => rejected ? { ok: false } : response(2500));
    const updatedTotals = [];
    const { root } = mount(t, DonationCounter, {
        ...counterProps, fundraising: { ...fundraising, raisedCents: 1250 },
        onRaisedCentsUpdated: amount => updatedTotals.push(amount),
    });
    await flushUpdates();

    assert.match(text(withClass(root, 'counter-total')[0]), /12,50/);
    assert.deepEqual(updatedTotals, []);

    rejected = false;
    await flow.refresh();

    assert.match(text(withClass(root, 'counter-total')[0]), /25/);
    assert.deepEqual(updatedTotals, [2500]);
});

for (const [raisedCents, percentage, width, reached] of [
    [0, '0', 0, false], [25000, '50', 50, false], [50000, '100', 100, true], [65000, '130', 100, true],
]) test(`a personal counter shows ${percentage}% of the student goal without overflowing its progress bar`, async (t) => {
    browser(t, () => response(raisedCents));
    const { root } = mount(t, DonationCounter, {
        fundraising: { raisedCents, goalCents: 50000 }, challenges: [], endpoint: '/account/donations/total',
        title: 'Persoonlijke donatieteller', showNextChallenge: false, showGoalStatus: true,
    });
    await flushUpdates();

    const progress = withClass(root, 'progress-track')[0];
    assert.match(text(root), /Persoonlijke donatieteller/);
    assert.match(text(root), /van €\s*500/);
    assert.equal(text(withClass(root, 'counter-percentage')[0]), `${percentage}% opgehaald`);
    assert.equal(progress.children[0].props.style.width, `${width}%`);
    assert.equal(progress.props['aria-valuenow'], width);
    assert.equal(progress.props['aria-valuemax'], '100');
    assert.equal(withClass(root, 'donation-counter--goal-reached').length, reached ? 1 : 0);
    assert.match(text(withClass(root, 'counter-goal-status')[0]), reached ? /Doel behaald/ : /Nog/);
    assert.doesNotMatch(text(root), /volgende challenge|Alle mijlpalen/);
});

test('the personal counter turns green at the goal and returns to red after a refunded donation', async (t) => {
    let total = 49999;
    const flow = browser(t, () => response(total));
    const { root } = mount(t, DonationCounter, {
        fundraising: { raisedCents: total, goalCents: 50000 }, challenges: [], endpoint: '/account/donations/total',
        showNextChallenge: false, showGoalStatus: true,
    });
    await flushUpdates();

    assert.equal(withClass(root, 'donation-counter--goal-reached').length, 0);
    assert.match(text(withClass(root, 'counter-goal-status')[0]), /Nog €\s*0,01/);
    total = 50000;
    await flow.refresh();

    assert.equal(withClass(root, 'donation-counter--goal-reached').length, 1);
    assert.match(text(withClass(root, 'counter-goal-status')[0]), /Doel behaald/);
    total = 45000;
    await flow.refresh();

    assert.equal(withClass(root, 'donation-counter--goal-reached').length, 0);
    assert.match(text(withClass(root, 'counter-goal-status')[0]), /Nog €\s*50/);
    assert.equal(withClass(root, 'progress-track')[0].children[0].props.style.width, '90%');
});

test('the donation card opens the official donation page without a simulated checkout', (t) => {
    const foundation = { name: 'Wensstichting Drenthe', donateUrl: 'https://wensstichtingdrenthe.nl/doneren' };
    const { root } = mount(t, DonationCard, { foundation });
    const donationLink = descendants(root, element => element.type === 'a' && text(element) === 'Doneer nu')[0];

    assert.equal(donationLink.props.href, foundation.donateUrl);
    assert.equal(donationLink.props.target, '_blank');
    assert.equal(donationLink.props.rel, 'noopener noreferrer');
    assert.equal(descendants(root, element => ['form', 'input', 'button'].includes(element.type)).length, 0);
    assert.match(text(root), /niet automatisch in deze teller verwerkt/);
    assert.doesNotMatch(text(root), /mollie|Betaalkoppeling in voorbereiding|Voorbeeld/);
});

test('challenge filters and the next milestone update when the polled total changes', async (t) => {
    let total = 0;
    const flow = browser(t, () => response(total));
    const { root } = mount(t, ChallengesPage, {
        event: { fundraising, challenges, foundation: { name: 'Stichting' }, images: {} },
        page: { name: 'challenges' }, links: { donationTotal: '/donations/total' },
    });
    await flushUpdates();
    assert.equal(withClass(root, 'challenge-card').length, 2);
    assert.doesNotMatch(text(root), /Voorbeeldmodus|testgegevens/);

    total = 6000;
    await flow.refresh();
    const unlockedFilter = descendants(root, element => element.type === 'button' && text(element).startsWith('Vrijgespeeld'))[0];
    unlockedFilter.props.onClick();
    await nextTick();

    assert.equal(withClass(root, 'challenge-card').length, 1);
    assert.match(text(withClass(root, 'challenge-card')[0]), /Eerste challenge/);
    assert.match(text(withClass(root, 'challenge-spotlight-copy')[0]), /Tweede challenge/);

    total = 15000;
    await flow.refresh();

    assert.equal(withClass(root, 'challenge-card').length, 2);
    assert.match(text(withClass(root, 'challenge-spotlight-copy')[0]), /Alle mijlpalen zijn behaald/);
});

function programItem(id, date = '2026-12-16') {
    return { id, date, time: '10:00–10:30', title: `Onderdeel ${id}`, description: 'Activiteit.' };
}

test('the full program groups both days and only renders supplied production details', (t) => {
    const { root } = mount(t, ProgramPage, {
        event: {
            schedule: [programItem('opening'), { ...programItem('avond'), presenter: 'Kevin' }, programItem('finale', '2026-12-17')],
            images: {}, startsAt: '2026-12-16T10:00:00+01:00', endsAt: '2026-12-17T10:00:00+01:00',
            location: { name: 'DCTerra', street: 'Straat', city: 'Emmen' },
        },
        page: { name: 'program' }, links: {},
    });

    assert.deepEqual(withClass(root, 'program-day-card').map(element => element.props.id), ['programma-2026-12-16', 'programma-2026-12-17']);
    assert.match(text(root), /woensdag 16 december/);
    assert.match(text(root), /donderdag 17 december/);
    assert.equal(withClass(root, 'timeline-description').length, 3);
    assert.equal(withClass(root, 'timeline-meta').length, 1);
    assert.match(text(root), /Presentator\(en\)Kevin/);
    assert.doesNotMatch(text(root), /OBS \/ regie \/ techniek/);
});

test('the homepage program preview stays limited to five slots', (t) => {
    const { root } = mount(t, ProgramCard, { items: Array.from({ length: 7 }, (_, index) => programItem(index)), href: '/programma' });

    assert.equal(withClass(root, 'timeline-description').length, 5);
    assert.match(text(root), /2 extra onderdelen/);
    assert.equal(descendants(root, element => element.type === 'a')[0].props.href, '/programma');
});

const countdownProps = { targetDate: '2026-12-16T10:00:00+01:00', endDate: '2026-12-17T10:00:00+01:00' };

function clock(t, initialTime) {
    const previous = Date.now;
    let currentTime = new Date(initialTime).getTime();
    Date.now = () => currentTime;
    after(t, () => { Date.now = previous; });
    return { advance: milliseconds => { currentTime += milliseconds; } };
}

test('the countdown updates each second and changes to a start message at the scheduled time', async (t) => {
    const time = clock(t, '2026-12-16T08:59:58Z');
    const flow = browser(t, () => response(0));
    const { root, unmount } = mount(t, CountdownTimer, countdownProps);

    assert.match(text(root), /We gaan live over/);
    assert.match(descendants(root, element => element.props.role === 'timer')[0].props['aria-label'], /2 seconden/);
    assert.equal([...flow.timers.values()][0].delay, 1000);

    time.advance(1000);
    await flow.refresh();
    assert.match(descendants(root, element => element.props.role === 'timer')[0].props['aria-label'], /1 seconden/);

    time.advance(1000);
    await flow.refresh();
    assert.equal(descendants(root, element => element.props.role === 'timer').length, 0);
    assert.match(text(root), /De starttijd is aangebroken/);
    unmount();
    assert.equal(flow.timers.size, 0);
});

test('a running livestream displays a live message instead of a countdown', (t) => {
    clock(t, '2026-12-16T12:00:00Z');
    browser(t, () => response(0));
    const { root } = mount(t, CountdownTimer, { ...countdownProps, isLive: true });

    assert.match(text(root), /We zijn live!/);
    assert.equal(descendants(root, element => element.props.role === 'timer').length, 0);
});

test('the countdown shows that the event has ended even if the live setting was not switched off', (t) => {
    clock(t, '2026-12-17T09:00:00Z');
    browser(t, () => response(0));
    const { root } = mount(t, CountdownTimer, { ...countdownProps, isLive: true });

    assert.match(text(root), /Deze livestream is afgelopen/);
    assert.doesNotMatch(text(root), /We zijn live!|We gaan live over/);
});

const managementEndpoints = {
    content: '/account/content', programStore: '/account/content/program',
    programUpdate: '/account/content/program/__PROGRAM_ITEM__', programDelete: '/account/content/program/__PROGRAM_ITEM__',
    challengeStore: '/account/content/challenges', challengeUpdate: '/account/content/challenges/__CHALLENGE__',
    challengeDelete: '/account/content/challenges/__CHALLENGE__', sponsorStore: '/account/content/sponsors',
    sponsorUpdate: '/account/content/sponsors/__SPONSOR__', sponsorDelete: '/account/content/sponsors/__SPONSOR__',
};

function contentApi(t, data, save = () => ({ data: { message: 'Opgeslagen.' } })) {
    const original = { get: axios.get, patch: axios.patch, post: axios.post, delete: axios.delete };
    const requests = [];
    axios.get = async url => {
        assert.equal(url, managementEndpoints.content);
        return { data: structuredClone(data) };
    };
    for (const method of ['patch', 'post', 'delete']) {
        axios[method] = async (url, payload) => {
            requests.push({ method, url, payload });
            return save(method, url, payload);
        };
    }
    after(t, () => Object.assign(axios, original));
    return requests;
}

async function clickButton(root, label) {
    const button = descendants(root, element => element.type === 'button' && text(element) === label)[0];
    assert.ok(button, `Button ${label} should be visible`);
    await button.props.onClick();
    await nextTick();
}

function field(root, label) {
    const element = descendants(root, item => item.type === 'label' && text(item).trimStart().startsWith(label))[0];
    assert.ok(element, `Field ${label} should be visible`);
    return descendants(element, item => ['input', 'textarea', 'select'].includes(item.type))[0];
}

async function submit(root) {
    const form = descendants(root, element => element.type === 'form')[0];
    await form.props.onSubmit({ preventDefault() {} });
    await nextTick();
}

test('organization can open and save challenge text without receiving an amount field or sending an amount', async (t) => {
    browser(t, () => response(0));
    const requests = contentApi(t, {
        program: [], sponsors: [], challenges: [{ id: 1, amountCents: 5000, name: 'Origineel', description: 'Beschrijving.' }],
    });
    const { root } = mount(t, ContentManagement, { endpoints: managementEndpoints, canManageChallengeLadder: false });
    await flushUpdates();
    await clickButton(root, 'Challenges');
    await clickButton(root, 'Aanpassen');

    assert.match(text(root), /Challenge aanpassen/);
    assert.doesNotMatch(text(root), /Bedrag in euro|Challenge toevoegen|Verwijderen/);
    field(root, 'Naam').props['onUpdate:modelValue']('Aangepast');
    await nextTick();
    await submit(root);

    assert.deepEqual(requests, [{ method: 'patch', url: '/account/content/challenges/1', payload: { name: 'Aangepast', description: 'Beschrijving.' } }]);
    assert.match(text(root), /Opgeslagen/);
});

test('admin challenge editing converts euro amounts to integer cents', async (t) => {
    browser(t, () => response(0));
    const requests = contentApi(t, {
        program: [], sponsors: [], challenges: [{ id: 1, amountCents: 5000, name: 'Challenge', description: 'Beschrijving.' }],
    });
    const { root } = mount(t, ContentManagement, { endpoints: managementEndpoints, canManageChallengeLadder: true });
    await flushUpdates();
    await clickButton(root, 'Challenges');
    await clickButton(root, 'Aanpassen');
    field(root, 'Bedrag in euro').props['onUpdate:modelValue']('75.25');
    await nextTick();

    await submit(root);

    assert.equal(requests[0].payload.amountCents, 7525);
    assert.equal(requests[0].method, 'patch');
});

test('a failed challenge save preserves the edited text and displays the validation message', async (t) => {
    browser(t, () => response(0));
    contentApi(t, {
        program: [], sponsors: [], challenges: [{ id: 1, amountCents: 5000, name: 'Challenge', description: 'Beschrijving.' }],
    }, () => { throw { response: { data: { errors: { name: ['Controleer de naam.'] } } } }; });
    const { root } = mount(t, ContentManagement, { endpoints: managementEndpoints, canManageChallengeLadder: false });
    await flushUpdates();
    await clickButton(root, 'Challenges');
    await clickButton(root, 'Aanpassen');
    field(root, 'Naam').props['onUpdate:modelValue']('Niet kwijtraken');
    await nextTick();

    await submit(root);

    assert.equal(field(root, 'Naam').value, 'Niet kwijtraken');
    assert.match(text(root), /Controleer de naam/);
});

test('a rejected delete does not clear the item that is being edited', async (t) => {
    browser(t, () => response(0));
    const requests = contentApi(t, {
        program: [{ ...programItem('opening'), id: 1 }, { ...programItem('finale'), id: 2 }], challenges: [], sponsors: [],
    }, () => { throw { response: { data: { message: 'Verwijderen geweigerd.' } } }; });
    const { root } = mount(t, ContentManagement, { endpoints: managementEndpoints, canManageChallengeLadder: true });
    await flushUpdates();
    await clickButton(root, 'Aanpassen');

    await clickButton(root, 'Verwijderen');

    assert.equal(requests[0].method, 'delete');
    assert.match(text(root), /Programmaonderdeel aanpassen/);
    assert.equal(field(root, 'Titel').value, 'Onderdeel opening');
    assert.match(text(root), /Verwijderen geweigerd/);
});

test('sponsor editing sends the selected tier and organization type', async (t) => {
    browser(t, () => response(0));
    const requests = contentApi(t, {
        program: [], challenges: [], sponsors: [{ id: 3, companyName: 'Sponsor A', sponsorshipType: 'gold', companyType: '' }],
    });
    const { root } = mount(t, ContentManagement, { endpoints: managementEndpoints, canManageChallengeLadder: false });
    await flushUpdates();
    await clickButton(root, 'Sponsoren');
    await clickButton(root, 'Aanpassen');
    field(root, 'Sponsorniveau').props['onUpdate:modelValue']('silver');
    field(root, 'Type organisatie').props['onUpdate:modelValue']('Horeca');
    await nextTick();

    await submit(root);

    assert.deepEqual(requests[0], {
        method: 'patch', url: '/account/content/sponsors/3',
        payload: { companyName: 'Sponsor A', sponsorshipType: 'silver', companyType: 'Horeca' },
    });
});

test('public share QR cards have downloads and the correct safe navigation links', (t) => {
    const { root } = mount(t, QrCards, { codes: {
        website: { url: '/home', image: 'data:image/svg+xml;base64,website' },
        foundationDonation: { url: 'https://example.com/doneren', image: 'data:image/svg+xml;base64,donation' },
    } });

    assert.equal(descendants(root, element => element.type === 'img').length, 2);
    assert.equal(descendants(root, element => element.type === 'a' && element.props.download).length, 2);
    assert.match(text(root), /Bezoek onze website|Doneer aan Wensstichting Drenthe/);
    const donationLink = descendants(root, element => element.type === 'a' && text(element) === 'Open de donatiepagina')[0];
    assert.equal(donationLink.props.href, 'https://example.com/doneren');
    assert.equal(donationLink.props.rel, 'noopener noreferrer');
    assert.equal(withClass(root, 'xl:grid-cols-3').length, 0);
    assert.doesNotMatch(text(root), /Inloggen — privé/);
});

function accountData(overrides = {}) {
    return {
        account: { name: 'Sam', email: 'sam@example.com', role: 'student', isPresent: false },
        canManage: false, canManageContent: false, canManageChallengeLadder: false, canScan: false,
        flyer: null, personalFundraising: null, codes: {}, shareableCodes: {}, history: [],
        endpoints: { ...managementEndpoints, requests: '/account/aanvragen', inviteSelected: '/account/studenten-uitnodigen', passkeys: '/account/passkeys', logout: '/uitloggen', personalDonationTotal: '/account/donations/total' },
        ...overrides,
    };
}

test('a participant sees only the general account tab and no simulated personal donation amount', (t) => {
    const { root } = mount(t, AccountDashboard, { qr: accountData() });

    assert.deepEqual(descendants(root, element => element.props.role === 'tab').map(text), ['Algemeen']);
    assert.match(text(root), /Nog geen bevestigde bedragen beschikbaar/);
    assert.doesNotMatch(text(root), /€\s*0|Registraties|Website-inhoud beheren/);
    assert.equal(descendants(root, element => element.type === 'a' && element.props.href === '/account/passkeys').length, 1);
});

test('a student account uses the shared counter style and refreshes only its personal donation total', async (t) => {
    const flow = browser(t, () => response(65000));
    const { root } = mount(t, AccountDashboard, { qr: accountData({ personalFundraising: { goalCents: 50000, raisedCents: 12345 } }) });

    assert.match(text(withClass(root, 'counter-total')[0]), /123,45/);
    await flushUpdates();

    assert.deepEqual(descendants(root, element => element.props.role === 'tab').map(text), ['Algemeen']);
    assert.equal(withClass(root, 'donation-counter').length, 1);
    assert.match(text(withClass(root, 'counter-total')[0]), /650/);
    assert.match(text(root), /Persoonlijke donatieteller/);
    assert.match(text(root), /130% opgehaald/);
    assert.match(text(root), /niet automatisch/);
    assert.doesNotMatch(text(root), /Nog geen bevestigde bedragen beschikbaar/);
    assert.deepEqual(flow.requests.map(request => request.url), ['/account/donations/total']);
});

test('public account names are rendered as text rather than executable HTML', async () => {
    const qr = accountData({ account: { name: '<script>alert(1)</script>', email: 'sam@example.com', role: 'student', isPresent: false } });

    const html = await renderToString(createSSRApp({ render: () => h(AccountDashboard, { qr }) }));

    assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>alert\(1\)<\/script>/);
});

test('an organization account receives website management but not registration management', (t) => {
    const { root } = mount(t, AccountDashboard, { qr: accountData({ canManageContent: true }) });

    assert.deepEqual(descendants(root, element => element.props.role === 'tab').map(text), ['Algemeen', 'Websitebeheer']);
    assert.equal(descendants(root, element => element.props.id === 'account-panel-website')[0].props.hidden, true);
});

test('account navigation loads registration management once and preserves edited roles when switching tabs', async (t) => {
    browser(t, () => response(0));
    const originalGet = axios.get;
    const originalPatch = axios.patch;
    const requests = [];
    const saved = [];
    axios.get = async url => {
        requests.push(url);
        return { data: { data: [{ id: 1, name: 'Nieuwe deelnemer', email: 'new@example.com', role: 'gast', registration_status: 'pending', approveUrl: '/account/aanvragen/1' }] } };
    };
    axios.patch = async (url, payload) => { saved.push({ url, payload }); return { data: { message: 'Goedgekeurd.' } }; };
    after(t, () => { axios.get = originalGet; axios.patch = originalPatch; });
    const { root } = mount(t, AccountDashboard, { qr: accountData({ canManage: true }) });
    assert.equal(requests.length, 0);

    await clickButton(root, 'Registratie');
    await flushUpdates();
    field(root, 'Accountrol').props['onUpdate:modelValue']('student');
    await nextTick();
    await clickButton(root, 'Algemeen');
    await clickButton(root, 'Registratie');

    assert.deepEqual(requests, ['/account/aanvragen']);
    assert.equal(descendants(root, element => element.props.id === 'account-panel-general')[0].props.hidden, true);
    assert.equal(descendants(root, element => element.props.id === 'account-panel-registrations')[0].props.hidden, false);
    await clickButton(root, 'Goedkeuren en uitnodigen');
    assert.deepEqual(saved, [{ url: '/account/aanvragen/1', payload: { role: 'student', status: 'approved' } }]);
    assert.match(text(root), /Goedgekeurd\./);
});

test('QR selection shows only one code and labels the attendance QR as private', async (t) => {
    const { root } = mount(t, AccountQrCodes, { codes: {
        donation: { url: '/studenten/doneren/public', image: 'data:image/svg+xml;base64,personal' },
        website: { url: '/', image: 'data:image/svg+xml;base64,website' },
        attendance: { url: '/aanwezigheid#token=private', image: 'data:image/svg+xml;base64,private' },
    } });

    assert.equal(descendants(root, element => element.type === 'img')[0].props.src, 'data:image/svg+xml;base64,personal');
    assert.equal(descendants(root, element => element.type === 'img').length, 1);
    await clickButton(root, 'Aanwezigheid');

    assert.equal(descendants(root, element => element.type === 'img').length, 1);
    assert.equal(descendants(root, element => element.type === 'img')[0].props.src, 'data:image/svg+xml;base64,private');
    assert.match(text(root), /aanwezigheids-QR is privé/);
    assert.doesNotMatch(text(root), /Bekijk mijn pagina/);
});

for (const [key, index, target] of [
    ['ArrowRight', 0, 1], ['ArrowRight', 1, 0], ['ArrowLeft', 0, 1], ['Home', 1, 0], ['End', 0, 1],
]) test(`section navigation handles ${key} from tab ${index} and moves focus to tab ${target}`, (t) => {
    const selected = [];
    const { root } = mount(t, SectionTabs, {
        id: 'test', label: 'Onderdeel kiezen', modelValue: 'general',
        items: [{ id: 'general', label: 'Algemeen' }, { id: 'website', label: 'Websitebeheer' }],
        'onUpdate:modelValue': id => selected.push(id),
    });
    const tabs = descendants(root, element => element.props.role === 'tab');
    let focused = false;
    let prevented = false;
    tabs[target].focus = () => { focused = true; };

    tabs[index].props.onKeydown({ key, preventDefault: () => { prevented = true; }, currentTarget: { parentElement: { querySelectorAll: () => tabs } } });

    assert.deepEqual(selected, [target === 0 ? 'general' : 'website']);
    assert.equal(focused, true);
    assert.equal(prevented, true);
});

function flyerBrowser(t, fails = false) {
    const previousImage = globalThis.Image;
    const previousDocument = globalThis.document;
    const originalCreate = URL.createObjectURL;
    const originalRevoke = URL.revokeObjectURL;
    const released = [];
    const state = { fails };
    globalThis.Image = class {
        naturalWidth = 1024;
        naturalHeight = 1536;
        set src(source) { queueMicrotask(() => state.fails ? this.onerror() : this.onload()); }
    };
    globalThis.document = {
        createElement: () => ({
            getContext: () => ({ drawImage() {}, fillRect() {} }),
            toBlob: callback => callback(new Blob(['flyer'], { type: 'image/png' })),
        }),
    };
    URL.createObjectURL = () => 'blob:personal-flyer';
    URL.revokeObjectURL = value => released.push(value);
    after(t, () => {
        globalThis.Image = previousImage;
        globalThis.document = previousDocument;
        URL.createObjectURL = originalCreate;
        URL.revokeObjectURL = originalRevoke;
    });
    return { state, released };
}

const personalFlyer = { templateUrl: '/flyer.png', donationCode: { url: '/studenten/doneren/public', image: 'data:image/svg+xml;base64,personal' } };

test('the flyer preview and download use the generated personal PNG and release it after leaving', async (t) => {
    const flow = flyerBrowser(t);
    const { root, unmount } = mount(t, PersonalFlyer, { flyer: personalFlyer });
    await flushUpdates();
    await flushUpdates();

    assert.equal(descendants(root, element => element.type === 'img')[0].props.src, 'blob:personal-flyer');
    const download = descendants(root, element => element.props.download)[0];
    assert.equal(download.props.href, 'blob:personal-flyer');
    assert.equal(download.props.download, '24uur-live-persoonlijke-flyer.png');
    assert.equal(descendants(root, element => element.type === 'a' && element.props.href === personalFlyer.donationCode.url).length, 1);
    unmount();
    assert.deepEqual(flow.released, ['blob:personal-flyer']);
});

test('a failed flyer generation shows an error and offers no original QR download, then allows retry', async (t) => {
    const flow = flyerBrowser(t, true);
    const { root } = mount(t, PersonalFlyer, { flyer: personalFlyer });
    await flushUpdates();
    await flushUpdates();

    assert.match(text(root), /kon niet worden geladen/);
    assert.equal(descendants(root, element => element.props.download).length, 0);
    assert.equal(descendants(root, element => element.type === 'img').length, 0);
    flow.state.fails = false;
    await clickButton(root, 'Opnieuw proberen');

    assert.equal(descendants(root, element => element.props.download)[0].props.href, 'blob:personal-flyer');
    assert.doesNotMatch(text(root), /kon niet worden geladen/);
});
