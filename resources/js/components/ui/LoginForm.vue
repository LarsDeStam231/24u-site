<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import ActionButton from './ActionButton.vue';
import { createPasskeyLoginPoller } from '../../passkeyLogin';

const props = defineProps({ endpoints: { type: Object, required: true }, links: { type: Object, required: true } });
const email = ref('');
const password = ref('');
const options = ref(null);
const busy = ref(false);
const error = ref('');
const hasLegacyToken = new URLSearchParams(window.location.hash.slice(1)).has('token');
const message = ref(hasLegacyToken ? 'Vaste inlog-QR-codes zijn vervallen. Gebruik je wachtwoord of een bestaande passkey.' : '');
if (hasLegacyToken) window.history.replaceState(null, '', window.location.pathname + window.location.search);
const pairing = ref(null);
const qrState = ref('idle');
const canRetry = ref(false);
const inputClass = 'w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950';
const poller = createPasskeyLoginPoller({
    consume: async () => (await axios.post(props.endpoints.pairConsume, {}, { timeout: 10000 })).data,
    onState: state => { qrState.value = state; },
    onApproved: redirect => {
        password.value = '';
        error.value = '';
        window.location.replace(redirect);
    },
    onFailure: failure => {
        error.value = failure.message;
        canRetry.value = failure.recoverable;
    },
});

async function perform(action) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        await action();
        return true;
    } catch (failure) {
        const errors = failure.response?.data?.errors;
        error.value = Object.values(errors || {}).flat()[0] || failure.response?.data?.message || 'Dit is niet gelukt. Probeer het opnieuw.';
        return false;
    } finally {
        busy.value = false;
    }
}

async function identify() {
    await perform(async () => {
        options.value = (await axios.post(props.endpoints.options, { email: email.value })).data;
    });
}

async function login() {
    await perform(async () => {
        try {
            const response = await axios.post(props.endpoints.login, { email: email.value, password: password.value });
            window.location.assign(response.data.redirect);
        } finally {
            password.value = '';
        }
    });
}

async function requestMail() {
    await perform(async () => {
        message.value = (await axios.post(props.endpoints.mail, { email: email.value })).data.message;
    });
}

async function createPairing() {
    if (busy.value || qrState.value === 'approved') return;
    poller.stop();
    await perform(async () => {
        pairing.value = (await axios.post(props.endpoints.pairCreate, { email: email.value })).data;
        password.value = '';
        canRetry.value = false;
        poller.start();
    });
}

async function changeEmail() {
    if (busy.value || qrState.value === 'approved') return;
    poller.stop();
    const succeeded = await perform(async () => {
        if (pairing.value) await axios.delete(props.endpoints.pairCancel);
        pairing.value = null;
        qrState.value = 'idle';
        options.value = null;
        password.value = '';
    });
    if (!succeeded && pairing.value) {
        qrState.value = 'failed';
        canRetry.value = true;
    }
}

async function usePassword() {
    if (busy.value || qrState.value === 'approved') return;
    poller.stop();
    const succeeded = await perform(async () => {
        await axios.delete(props.endpoints.pairCancel);
        pairing.value = null;
        qrState.value = 'idle';
        canRetry.value = false;
    });
    if (!succeeded) {
        qrState.value = 'failed';
        canRetry.value = true;
    }
}

function retryCompletion() {
    error.value = '';
    canRetry.value = false;
    poller.start();
}

function checkOnReturn() {
    if (!document.hidden) poller.check();
}

onMounted(() => {
    window.addEventListener('focus', checkOnReturn);
    document.addEventListener('visibilitychange', checkOnReturn);
});

onUnmounted(() => {
    poller.stop();
    window.removeEventListener('focus', checkOnReturn);
    document.removeEventListener('visibilitychange', checkOnReturn);
});
</script>

<template>
    <section class="content-card mx-auto grid max-w-xl gap-6 p-6 sm:p-8">
        <h2 class="text-2xl">Inloggen</h2>
        <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>
        <p v-if="message" role="status" class="rounded border border-green-300 bg-green-50 p-4 text-green-950">{{ message }}</p>
        <form v-if="!options" class="grid gap-5" :aria-busy="busy" @submit.prevent="identify">
            <label for="login-email" class="grid gap-2 font-bold">E-mailadres
                <input id="login-email" v-model="email" :class="inputClass" name="email" type="email" required maxlength="255" autocomplete="username" :disabled="busy" />
            </label>
            <ActionButton type="submit" :disabled="busy">Verder</ActionButton>
        </form>
        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="break-all font-bold">{{ email }}</p>
                <button type="button" class="underline" :disabled="busy || qrState === 'approved'" @click="changeEmail">Ander e-mailadres</button>
            </div>
            <div v-if="qrState !== 'idle'" class="grid gap-4" :aria-busy="qrState === 'waiting' || qrState === 'retrying' || qrState === 'approved'">
                <h3 class="text-xl">Inloggen met je passkey</h3>
                <p v-if="qrState === 'waiting'" role="status">Wachten op bevestiging op je telefoon. Na een geldige passkey ga je direct naar je account, zonder wachtwoord.</p>
                <p v-else-if="qrState === 'retrying'" role="status">De verbinding is tijdelijk onderbroken. We proberen automatisch opnieuw je inlog af te ronden.</p>
                <p v-else-if="qrState === 'approved'" role="status">Passkey geverifieerd. Je bent ingelogd en wordt doorgestuurd naar je account.</p>
                <template v-if="pairing && qrState !== 'approved'">
                    <img :src="pairing.qr.image" alt="Tijdelijke QR-code om met een bestaande passkey in te loggen" width="320" height="320" class="mx-auto w-full max-w-80 bg-white p-2" />
                    <p class="text-center">Code van deze computer: <strong class="block text-3xl tracking-widest">{{ pairing.code }}</strong></p>
                    <p>Scan met je telefoon, vul daar deze code in en bevestig met je bestaande passkey. De QR is vijf minuten geldig. Hiermee voeg je geen passkey toe.</p>
                </template>
                <template v-if="qrState === 'failed'">
                    <ActionButton v-if="canRetry" :disabled="busy" @click="retryCompletion">Afronden opnieuw proberen</ActionButton>
                    <ActionButton :disabled="busy" @click="createPairing">Nieuwe QR-code maken</ActionButton>
                </template>
                <ActionButton v-if="options.hasPassword && qrState !== 'approved'" variant="outline" :disabled="busy" @click="usePassword">Annuleren en toch mijn wachtwoord gebruiken</ActionButton>
            </div>
            <template v-else>
                <form v-if="options.hasPassword" class="grid gap-5" :aria-busy="busy" @submit.prevent="login">
                    <input :value="email" name="email" type="email" autocomplete="username" hidden />
                    <label for="login-password" class="grid gap-2 font-bold">Wachtwoord
                        <input id="login-password" v-model="password" :class="inputClass" name="password" type="password" required maxlength="4096" autocomplete="current-password" :disabled="busy" />
                    </label>
                    <ActionButton type="submit" :disabled="busy">Inloggen met wachtwoord</ActionButton>
                </form>
                <p v-else>Heb je nog geen wachtwoord? Vraag zelf een e-mail aan om een wachtwoord in te stellen. Je account moet eerst goedgekeurd zijn.</p>
                <ActionButton variant="outline" :disabled="busy" @click="requestMail">{{ options.hasPassword ? 'Wachtwoord vergeten? Stuur een e-mail' : 'Stuur een e-mail om een wachtwoord in te stellen' }}</ActionButton>
                <ActionButton v-if="options.hasPasskeys" variant="outline" :disabled="busy" @click="createPairing">Liever inloggen via QR met mijn passkey</ActionButton>
            </template>
        </template>
        <p>Nog geen account? <a :href="links.registration" class="font-bold underline">Registreren</a></p>
    </section>
</template>
