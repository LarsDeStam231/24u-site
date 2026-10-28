<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { browserSupportsWebAuthn, startAuthentication, startRegistration } from '@simplewebauthn/browser';
import ActionButton from './ActionButton.vue';

const props = defineProps({ mode: { type: String, required: true }, token: { type: String, required: true }, endpoints: { type: Object, required: true } });
const email = ref('');
const password = ref('');
const code = ref('');
const name = ref('Mijn telefoon');
const verified = ref(false);
const done = ref(false);
const busy = ref(false);
const error = ref('');
const supported = window.isSecureContext && browserSupportsWebAuthn();
const inputClass = 'w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950';

async function perform(action) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await action();
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0] || failure.response?.data?.message
            || (failure.name === 'NotAllowedError' ? 'De verificatie is geannuleerd of verlopen. Probeer opnieuw.' : 'De passkey kon niet worden geverifieerd. Probeer opnieuw.');
    } finally {
        password.value = '';
        busy.value = false;
    }
}

async function authorize() {
    await perform(async () => {
        await axios.post(props.endpoints.registrationAuthorize, { token: props.token, code: code.value, email: email.value, password: password.value });
        verified.value = true;
    });
}

async function register() {
    await perform(async () => {
        const options = (await axios.post(props.endpoints.registrationOptions, { token: props.token })).data;
        const credential = await startRegistration({ optionsJSON: options });
        await axios.post(props.endpoints.registrationStore, { token: props.token, name: name.value, credential: JSON.stringify(credential) });
        done.value = true;
    });
}

async function authenticate() {
    await perform(async () => {
        const options = (await axios.post(props.endpoints.pairOptions, { token: props.token, code: code.value })).data;
        const credential = await startAuthentication({ optionsJSON: options });
        await axios.post(props.endpoints.pairApprove, { token: props.token, credential: JSON.stringify(credential) });
        done.value = true;
    });
}
</script>

<template>
    <section class="content-card mx-auto grid w-full max-w-xl gap-5 p-6 sm:p-8">
        <h2 class="text-2xl">{{ mode === 'pair' ? 'Bevestig het inloggen op jouw computer' : 'Voeg een passkey toe aan jouw account' }}</h2>
        <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>
        <p v-if="!supported">Passkeys vereisen een ondersteunde browser en een beveiligde HTTPS-verbinding. Open de link in Safari of Chrome via het vaste websiteadres.</p>
        <p v-else-if="!token">Scan de volledige QR-code vanaf je eigen computer.</p>
        <p v-else-if="done" role="status">{{ mode === 'pair' ? 'Bevestigd. Je oorspronkelijke computer wordt ingelogd. Je kunt deze pagina sluiten.' : 'Passkey opgeslagen. Vernieuw het passkeyoverzicht op je computer. Je kunt deze pagina sluiten.' }}</p>
        <template v-else-if="mode === 'pair'">
            <p>Dit verzoek logt een andere browser in met een bestaande passkey. Er wordt geen passkey toegevoegd. Bevestig alleen een verzoek dat je zelf bent gestart.</p>
            <form class="grid gap-5" @submit.prevent="authenticate">
                <label class="grid gap-2 font-bold">Zescijferige code van jouw computer<input v-model="code" :class="inputClass" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" /></label>
                <ActionButton type="submit" :disabled="busy">Bevestigen met mijn passkey</ActionButton>
            </form>
        </template>
        <template v-else>
            <p>Deze QR voegt een nieuwe passkey toe. Bevestig eerst het account waarvoor je de QR vanuit je accountinstellingen hebt aangemaakt.</p>
            <form v-if="!verified" class="grid gap-5" @submit.prevent="authorize">
                <label class="grid gap-2 font-bold">Zescijferige code van jouw computer<input v-model="code" :class="inputClass" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" /></label>
                <label class="grid gap-2 font-bold">E-mailadres<input v-model="email" :class="inputClass" type="email" required maxlength="255" autocomplete="username" /></label>
                <label class="grid gap-2 font-bold">Wachtwoord<input v-model="password" :class="inputClass" type="password" required maxlength="4096" autocomplete="current-password" /></label>
                <ActionButton type="submit" :disabled="busy">Controleer mijn account</ActionButton>
            </form>
            <form v-else class="grid gap-5" @submit.prevent="register">
                <label class="grid gap-2 font-bold">Herkenbare naam<input v-model="name" :class="inputClass" required maxlength="120" /></label>
                <p>Je telefoon vraagt om vingerafdruk, gezichtsherkenning of toestelcode. De passkey kan via je Apple- of Google-account worden gesynchroniseerd.</p>
                <ActionButton type="submit" :disabled="busy">Passkey aanmaken</ActionButton>
            </form>
        </template>
    </section>
</template>
