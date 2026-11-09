<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import PageHeading from '../components/layout/PageHeading.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import AccountDashboard from '../components/account/AccountDashboard.vue';
import QrCards from '../components/ui/QrCards.vue';
import LoginForm from '../components/ui/LoginForm.vue';
import PasskeyPhone from '../components/ui/PasskeyPhone.vue';
import PasskeySettings from '../components/ui/PasskeySettings.vue';

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

const password = ref('');
const name = ref('');
const email = ref('');
const authenticated = ref(props.qr.authenticated);
const busy = ref(false);
const message = ref('');
const error = ref('');
const codes = ref(props.qr.codes || {});
const scanResult = ref(null);
const hasCodes = computed(() => Object.keys(codes.value).length > 0);
const inputClass = 'w-full rounded border border-slate-300 bg-white p-3 text-slate-950';

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
        try {
            await axios.post(endpoints.passwordLogin, { email: email.value, password: password.value });
        } finally {
            password.value = '';
        }
        emit('authenticated');
        authenticated.value = true;
        message.value = 'Ingelogd. Je kunt nu verdergaan.';
    });
}

async function activate() {
    await perform(async () => {
        const response = await axios.post(endpoints.activate, { token: scannedToken.value, name: name.value });
        scannedToken.value = '';
        codes.value = response.data.codes;
        message.value = 'Activering gelukt. Je kunt nu inloggen met een wachtwoord of dit via de inlogpagina instellen.';
    });
}

async function recordAttendance(action) {
    await perform(async () => {
        const response = await axios.post(endpoints.attendanceStore, { token: tokenFrom(scannedToken.value), action });
        scanResult.value = response.data;
        message.value = response.data.message;
    });
}
</script>

<template>
    <div>
    <PageHeading v-if="qr.mode !== 'account'" :page="page" :event="event" />
    <div class="page-container grid gap-6" :class="qr.mode === 'account' ? 'py-6' : 'py-10'">
        <p v-if="message" role="status" class="rounded border border-green-300 bg-green-50 p-4 text-green-950">{{ message }}</p>
        <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>

        <template v-if="qr.mode === 'login'">
            <p v-if="scannedToken" class="rounded border border-amber-300 bg-amber-50 p-4">Vaste privé-inlog-QR-codes zijn vervallen. Gebruik je wachtwoord of een al gekoppelde passkey.</p>
            <LoginForm :links="links" :endpoints="{ options: endpoints.loginOptions, login: endpoints.passwordLogin, mail: endpoints.passwordMail, pairCreate: endpoints.pairCreate, pairConsume: endpoints.pairConsume, pairCancel: endpoints.pairCancel }" />
        </template>

        <PasskeyPhone v-if="qr.mode === 'pair' || qr.mode === 'passkey-registration'" :mode="qr.mode" :token="scannedToken" :endpoints="endpoints" />
        <PasskeySettings v-if="qr.mode === 'passkeys'" :passkeys="qr.passkeys" :endpoints="endpoints" />

        <section v-if="qr.mode === 'activate' && !hasCodes" class="content-card mx-auto grid w-full max-w-xl gap-5 p-6">
            <h2 class="text-2xl">Bevestig jouw activering</h2>
            <p>Een activatielink werkt één keer en verloopt na 24 uur. Hiermee activeer je je aanwezigheids- en eventuele donatiecode, niet een inlogcode.</p>
            <form v-if="scannedToken" class="grid gap-4" @submit.prevent="activate">
                <label class="grid gap-2 font-bold">Jouw openbare naam<input v-model="name" :class="inputClass" required maxlength="120" autocomplete="name" /></label>
                <p>Deze naam verschijnt op je persoonlijke donatiepagina. Je e-mailadres en studentnummer blijven privé.</p>
                <ActionButton type="submit" :disabled="busy">Activeer mijn QR-codes</ActionButton>
            </form>
            <p v-else>Open de volledige activatielink uit je e-mail.</p>
            <ActionButton :href="endpoints.loginPage" variant="outline">Terug naar inloggen</ActionButton>
        </section>

        <template v-if="qr.mode === 'activate' && hasCodes">
            <p>Je codes zijn geactiveerd. Deel alleen je donatie-QR openbaar.</p>
            <QrCards :codes="codes" />
            <ActionButton :href="links.login">Naar inloggen</ActionButton>
        </template>

        <section v-if="qr.mode === 'attendance'" class="content-card mx-auto grid w-full max-w-xl gap-5 p-6">
            <template v-if="!authenticated">
                <h2 class="text-2xl">Log eerst op je telefoon in</h2>
                <p>Log in met je e-mailadres en wachtwoord om aanwezigheid te registreren.</p>
                <form class="grid gap-4" @submit.prevent="login">
                    <label class="grid gap-2 font-bold">E-mailadres<input v-model="email" :class="inputClass" type="email" autocomplete="username" required /></label>
                    <label class="grid gap-2 font-bold">Wachtwoord<input v-model="password" :class="inputClass" type="password" autocomplete="current-password" required /></label>
                    <ActionButton type="submit" :disabled="busy">Inloggen op mijn telefoon</ActionButton>
                </form>
                <ActionButton :href="links.login" variant="outline">Andere inlogopties</ActionButton>
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

        <AccountDashboard v-if="qr.mode === 'account'" :qr="qr" />

        <section v-if="qr.mode === 'donation'" class="content-card mx-auto grid w-full max-w-2xl gap-5 p-6">
            <h2 class="text-3xl">Steun de actie van {{ qr.studentName }}</h2>
            <p>Samen helpen we {{ event.foundation.name }} tijdens DCTerra 24 Uur Live.</p>
            <ActionButton :href="qr.donateUrl" rel="noopener noreferrer">Doneer via het goede doel</ActionButton>
            <p>Je betaalt rechtstreeks op de website van het goede doel. We ontvangen nog geen betaalbevestigingen: je donatie kan daarom nog niet aan deze student of een ranking worden toegevoegd.</p>
        </section>
    </div>
    </div>
</template>
