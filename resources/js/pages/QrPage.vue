<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import PageHeading from '../components/layout/PageHeading.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import QrCards from '../components/ui/QrCards.vue';

const props = defineProps({
    page: { type: Object, required: true }, event: { type: Object, required: true },
    qr: { type: Object, required: true }, links: { type: Object, required: true },
});
const emit = defineEmits(['authenticated']);

const endpoints = props.qr.endpoints;
const fragment = new URLSearchParams(window.location.hash.slice(1));
const scannedToken = ref(fragment.get('token') || '');
if (window.location.hash) {
    window.history.replaceState(null, '', window.location.pathname + window.location.search);
}

const loginToken = ref(props.qr.mode === 'login' ? scannedToken.value : '');
const name = ref('');
const email = ref('');
const confirmationCode = ref('');
const authenticated = ref(props.qr.authenticated);
const busy = ref(false);
const message = ref('');
const error = ref('');
const codes = ref(props.qr.codes || {});
const pairing = ref(null);
const accounts = ref(null);
const selected = ref([]);
const scanResult = ref(null);
let pollTimer;
let pairingVersion = 0;
let disposed = false;
const hasCodes = computed(() => Object.keys(codes.value).length > 0);
const inputClass = 'w-full rounded border border-slate-300 bg-white p-3 text-slate-950';
const roles = ['student', 'organisatie', 'vrijwilliger', 'donateur', 'gast', 'admin'];

function tokenFrom(value) {
    try {
        return new URLSearchParams(new URL(value).hash.slice(1)).get('token') || value.trim();
    } catch {
        return value.trim();
    }
}

async function perform(action) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        await action();
    } catch (failure) {
        error.value = failure.response?.data?.message || 'Dit is niet gelukt. Controleer je verbinding en probeer opnieuw.';
    } finally {
        busy.value = false;
    }
}

async function login() {
    await perform(async () => {
        const response = await axios.post(props.qr.studentOnly ? endpoints.studentLogin : endpoints.login, { token: tokenFrom(loginToken.value) });
        loginToken.value = '';
        emit('authenticated');
        if (props.qr.mode === 'pair' || props.qr.mode === 'attendance') {
            authenticated.value = true;
            message.value = 'Ingelogd. Je kunt nu verdergaan.';
        } else {
            window.location.assign(response.data.redirect);
        }
    });
}

async function activate() {
    await perform(async () => {
        const response = await axios.post(endpoints.activate, { token: scannedToken.value, name: name.value });
        scannedToken.value = '';
        codes.value = response.data.codes;
        authenticated.value = true;
        emit('authenticated');
        message.value = 'Activering gelukt. Download je privé-inlog-QR nu; deze wordt niet opnieuw getoond.';
    });
}

async function mailRequest() {
    await perform(async () => {
        const response = await axios.post(endpoints.invite, { email: email.value || props.qr.account?.email });
        message.value = response.data.message;
    });
}

async function pollPairing(version) {
    if (disposed || version !== pairingVersion) return;
    try {
        const response = await axios.post(endpoints.pairConsume);
        if (disposed || version !== pairingVersion) return;
        if (response.data.status === 'approved') {
            window.location.assign(response.data.redirect);
            return;
        }
        pollTimer = window.setTimeout(() => pollPairing(version), 2000);
    } catch {
        if (disposed || version !== pairingVersion) return;
        pairing.value = null;
        error.value = 'De koppeling is verlopen of niet meer geldig. Maak een nieuwe koppel-QR.';
    }
}

async function createPairing() {
    if (busy.value) return;
    const version = ++pairingVersion;
    window.clearTimeout(pollTimer);
    await perform(async () => {
        const response = await axios.post(endpoints.pairCreate, { student_only: Boolean(props.qr.studentOnly) });
        pairing.value = response.data;
        pollTimer = window.setTimeout(() => pollPairing(version), 2000);
    });
}

async function approvePairing() {
    await perform(async () => {
        const response = await axios.post(endpoints.pairApprove, { token: tokenFrom(scannedToken.value), code: confirmationCode.value });
        message.value = response.data.message;
        scannedToken.value = '';
    });
}

async function recordAttendance(action) {
    await perform(async () => {
        const response = await axios.post(endpoints.attendanceStore, { token: tokenFrom(scannedToken.value), action });
        scanResult.value = response.data;
        message.value = response.data.message;
    });
}

async function loadAccounts(url = endpoints.requests) {
    const response = await axios.get(url);
    accounts.value = response.data;
    selected.value = [];
}

async function manageAccount(account, status) {
    await perform(async () => {
        const response = status
            ? await axios.patch(account.approveUrl, { role: account.role, status })
            : await axios.post(account.inviteUrl);
        message.value = response.data.message;
        await loadAccounts();
    });
}

async function inviteSelected() {
    await perform(async () => {
        const response = await axios.post(endpoints.inviteSelected, { ids: selected.value });
        message.value = response.data.message;
        selected.value = [];
    });
}

async function logout() {
    await perform(async () => {
        const response = await axios.post(endpoints.logout);
        window.location.assign(response.data.redirect);
    });
}

onMounted(() => {
    if (props.qr.canManage) perform(() => loadAccounts());
});
onUnmounted(() => {
    disposed = true;
    window.clearTimeout(pollTimer);
});
</script>

<template>
    <div>
    <PageHeading :page="page" :event="event" />
    <div class="page-container grid gap-6 py-10">
        <p v-if="message" role="status" class="rounded border border-green-300 bg-green-50 p-4 text-green-950">{{ message }}</p>
        <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>

        <template v-if="qr.mode === 'login'">
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="content-card grid content-start gap-5 p-6">
                    <h2 class="text-2xl">Inloggen op dit apparaat</h2>
                    <p>Scan je privé-inlog-QR met de camera van je telefoon en bevestig hier. Je kunt ook je privé-inloglink hieronder plakken.</p>
                    <form class="grid gap-4" @submit.prevent="login">
                        <label class="grid gap-2 font-bold">Privé-inlogcode of inloglink<input v-model="loginToken" :class="inputClass" type="password" required autocomplete="off" /></label>
                        <ActionButton type="submit" :disabled="busy">Inloggen</ActionButton>
                    </form>
                    <a :href="endpoints.studentsLoginPage" class="underline">Aparte studenteninlog</a>
                    <ActionButton :href="links.passwordLogin" variant="outline">Inloggen met e-mail en wachtwoord</ActionButton>
                    <ActionButton v-if="qr.authenticated" :href="endpoints.account" variant="outline">Je bent al ingelogd: naar mijn account</ActionButton>
                </section>
                <section class="content-card grid content-start gap-5 p-6">
                    <h2 class="text-2xl">Inloggen op je computer</h2>
                    <p>Log eerst op je telefoon in. Maak hier een tijdelijke QR-code, scan die met je telefoon en vul daar de zescijferige code van dit scherm in.</p>
                    <ActionButton :disabled="busy" @click="createPairing">Nieuwe koppel-QR maken</ActionButton>
                    <template v-if="pairing">
                        <img :src="pairing.qr.image" alt="Tijdelijke QR om deze computer in te loggen" width="320" height="320" class="mx-auto max-w-80 bg-white p-2" />
                        <p class="text-center">Bevestigingscode: <strong class="block text-4xl tracking-widest">{{ pairing.code }}</strong></p>
                        <p>Geldig voor twee minuten. Bevestig alleen een computer die je zelf gebruikt.</p>
                    </template>
                </section>
            </div>
            <section class="content-card grid gap-4 p-6">
                <h2 class="text-2xl">Bestaand account, maar geen QR-code?</h2>
                <p>Heb je al een goedgekeurd account? Vraag hieronder een activatielink aan om je QR-codes te ontvangen of je privé-inlog-QR te vervangen.</p>
                <form class="grid max-w-xl gap-4" @submit.prevent="mailRequest">
                    <label class="grid gap-2 font-bold">E-mailadres<input v-model="email" :class="inputClass" type="email" required autocomplete="email" /></label>
                    <div class="flex flex-wrap gap-3">
                        <ActionButton type="submit" :disabled="busy">Stuur activatielink</ActionButton>
                    </div>
                </form>
                <p>Nog geen account? <a :href="links.registration" class="font-bold underline">Registreren</a></p>
            </section>
        </template>

        <section v-if="qr.mode === 'activate' && !hasCodes" class="content-card mx-auto grid w-full max-w-xl gap-5 p-6">
            <h2 class="text-2xl">Bevestig jouw activering</h2>
            <p>Een activatielink werkt één keer en verloopt na 24 uur. Alleen door hieronder te bevestigen worden je codes aangemaakt of wordt je oude inlog-QR vervangen.</p>
            <form v-if="scannedToken" class="grid gap-4" @submit.prevent="activate">
                <label class="grid gap-2 font-bold">Jouw openbare naam<input v-model="name" :class="inputClass" required maxlength="120" autocomplete="name" /></label>
                <p>Deze naam verschijnt op je persoonlijke donatiepagina. Je e-mailadres en studentnummer blijven privé.</p>
                <ActionButton type="submit" :disabled="busy">Activeer mijn QR-codes</ActionButton>
            </form>
            <p v-else>Open de volledige activatielink uit je e-mail.</p>
            <ActionButton :href="endpoints.loginPage" variant="outline">Terug naar inloggen</ActionButton>
        </section>

        <template v-if="qr.mode === 'activate' && hasCodes">
            <p class="rounded border border-amber-300 bg-amber-50 p-4 font-bold">Download je privé-inlog-QR voordat je deze pagina sluit. Deel alleen je donatie-QR openbaar.</p>
            <QrCards :codes="codes" />
            <ActionButton :href="endpoints.account">Naar mijn account</ActionButton>
        </template>

        <section v-if="qr.mode === 'pair' || qr.mode === 'attendance'" class="content-card mx-auto grid w-full max-w-xl gap-5 p-6">
            <template v-if="!authenticated">
                <h2 class="text-2xl">Log eerst op je telefoon in</h2>
                <p>Log hieronder in met jouw privé-inlogcode. Of scan eerst je privé-inlog-QR en scan daarna deze {{ qr.mode === 'pair' ? 'computer-QR' : 'aanwezigheids-QR' }} opnieuw.</p>
                <form class="grid gap-4" @submit.prevent="login">
                    <label class="grid gap-2 font-bold">Privé-inlogcode of inloglink<input v-model="loginToken" :class="inputClass" type="password" autocomplete="off" required /></label>
                    <ActionButton type="submit" :disabled="busy">Inloggen op mijn telefoon</ActionButton>
                </form>
                <ActionButton :href="endpoints.loginPage" variant="outline">Activatielink aanvragen</ActionButton>
            </template>
            <template v-else-if="qr.mode === 'pair'">
                <h2 class="text-2xl">Bevestig jouw eigen computer</h2>
                <p>Vul de zescijferige code in die op je eigen computerscherm staat. Bevestig nooit een QR-code die iemand anders je toestuurt.</p>
                <form class="grid gap-4" @submit.prevent="approvePairing">
                    <label v-if="!scannedToken" class="grid gap-2 font-bold">Koppellink<input v-model="scannedToken" :class="inputClass" required autocomplete="off" /></label>
                    <label class="grid gap-2 font-bold">Code van je computer<input v-model="confirmationCode" :class="inputClass" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" /></label>
                    <ActionButton type="submit" :disabled="busy">Log mijn computer in</ActionButton>
                </form>
            </template>
            <template v-else>
                <h2 class="text-2xl">In- of uitchecken</h2>
                <p>Alleen beheerders, organisatieleden en vrijwilligers mogen tijdens het evenement aanwezigheid registreren.</p>
                <label class="grid gap-2 font-bold">Aanwezigheidscode of gescande link<input v-model="scannedToken" :class="inputClass" required autocomplete="off" /></label>
                <div class="flex flex-wrap gap-3">
                    <ActionButton :disabled="busy || !scannedToken" @click="recordAttendance('check-in')">Inchecken</ActionButton>
                    <ActionButton :disabled="busy || !scannedToken" variant="outline" @click="recordAttendance('check-out')">Uitchecken</ActionButton>
                </div>
                <p v-if="scanResult"><strong>{{ scanResult.name }}</strong> is nu {{ scanResult.isPresent ? 'aanwezig' : 'afwezig' }}.</p>
            </template>
        </section>

        <template v-if="qr.mode === 'account'">
            <section class="content-card grid gap-4 p-6">
                <h2 class="text-2xl">Welkom, {{ qr.account.name || 'deelnemer' }}</h2>
                <p>{{ qr.account.email }} · {{ qr.account.role }}</p>
                <p v-if="qr.account.studentNumber">Studentnummer: {{ qr.account.studentNumber }}</p>
                <p>Aanwezigheid: <strong>{{ qr.account.isPresent ? 'aanwezig' : 'afwezig' }}</strong></p>
                <div class="flex flex-wrap gap-3">
                    <ActionButton v-if="qr.canScan" :href="endpoints.attendance">Aanwezigheid registreren</ActionButton>
                    <ActionButton variant="outline" :disabled="busy" @click="mailRequest">Nieuwe privé-inlog-QR aanvragen via e-mail</ActionButton>
                    <ActionButton variant="outline" :disabled="busy" @click="logout">Uitloggen</ActionButton>
                </div>
            </section>
            <QrCards v-if="hasCodes" :codes="codes" />
            <p v-else>Activeer eerst je QR-codes via de link in je e-mail.</p>
            <section v-if="qr.account.role === 'student'" class="content-card grid gap-3 p-6">
                <h2 class="text-2xl">Mijn donaties en ranking</h2>
                <p>Nog niet beschikbaar: het goede doel ontvangt betalingen via zijn eigen betaalpagina. Zolang betaalbevestigingen ontbreken, tonen we geen onbevestigde bedragen of ranglijst.</p>
            </section>
            <section v-if="qr.history?.length" class="content-card grid gap-3 p-6">
                <h2 class="text-2xl">Mijn aanwezigheidsgeschiedenis</h2>
                <ul class="grid gap-2"><li v-for="(entry, index) in qr.history" :key="index">{{ entry.created_at }} — {{ entry.action === 'check-in' ? 'Ingecheckt' : 'Uitgecheckt' }}</li></ul>
            </section>
            <section v-if="qr.canManage" class="content-card grid gap-5 p-6">
                <h2 class="text-2xl">Registratieaanvragen en bestaande studenten</h2>
                <p>Ken eerst de juiste rol toe en keur de aanvraag goed. De gebruiker ontvangt daarna een activatiemail.</p>
                <ActionButton :disabled="busy || !selected.length" @click="inviteSelected">Nodig geselecteerde studenten uit ({{ selected.length }})</ActionButton>
                <div v-if="accounts" class="grid gap-4">
                    <article v-for="account in accounts.data" :key="account.id" class="grid gap-3 rounded border border-slate-200 p-4 lg:grid-cols-2">
                        <div>
                            <p class="font-bold">{{ account.name || account.email }}</p>
                            <p>{{ account.email }} — {{ account.registration_status }}</p>
                            <label v-if="account.role === 'student' && account.registration_status === 'approved'" class="flex items-center gap-2"><input v-model="selected" type="checkbox" :value="account.id" /> Uitnodigen</label>
                        </div>
                        <div class="grid gap-3">
                            <label class="grid gap-2">Rol<select v-model="account.role" :class="inputClass"><option v-for="role in roles" :key="role" :value="role">{{ role }}</option></select></label>
                            <div class="flex flex-wrap gap-2">
                                <ActionButton :disabled="busy" @click="manageAccount(account, 'approved')">Goedkeuren en uitnodigen</ActionButton>
                                <ActionButton variant="outline" :disabled="busy" @click="manageAccount(account, 'rejected')">Afwijzen</ActionButton>
                                <ActionButton v-if="account.registration_status === 'approved'" variant="outline" :disabled="busy" @click="manageAccount(account)">Activatiemail opnieuw</ActionButton>
                            </div>
                        </div>
                    </article>
                    <p v-if="!accounts.data.length">Geen accounts gevonden.</p>
                    <div class="flex gap-3">
                        <ActionButton v-if="accounts.prev_page_url" variant="outline" :disabled="busy" @click="perform(() => loadAccounts(accounts.prev_page_url))">Vorige</ActionButton>
                        <ActionButton v-if="accounts.next_page_url" variant="outline" :disabled="busy" @click="perform(() => loadAccounts(accounts.next_page_url))">Volgende</ActionButton>
                    </div>
                </div>
            </section>
        </template>

        <section v-if="qr.mode === 'donation'" class="content-card mx-auto grid w-full max-w-2xl gap-5 p-6">
            <h2 class="text-3xl">Steun de actie van {{ qr.studentName }}</h2>
            <p>Samen helpen we {{ event.foundation.name }} tijdens DCTerra 24 Uur Live.</p>
            <ActionButton :href="qr.donateUrl" rel="noopener noreferrer">Doneer via het goede doel</ActionButton>
            <p>Je betaalt rechtstreeks op de website van het goede doel. We ontvangen nog geen betaalbevestigingen: je donatie kan daarom nog niet aan deze student of een ranking worden toegevoegd.</p>
        </section>
    </div>
    </div>
</template>
