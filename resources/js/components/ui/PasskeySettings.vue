<script setup>
import { ref } from 'vue';
import axios from 'axios';
import ActionButton from './ActionButton.vue';

const props = defineProps({ passkeys: { type: Array, required: true }, endpoints: { type: Object, required: true } });
const keys = ref([...props.passkeys]);
const pairing = ref(null);
const password = ref('');
const busy = ref(false);
const error = ref('');
const message = ref('');

async function perform(action) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await action();
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0] || failure.response?.data?.message || 'Dit is niet gelukt. Probeer opnieuw.';
    } finally {
        password.value = '';
        busy.value = false;
    }
}

async function create() {
    await perform(async () => {
        pairing.value = (await axios.post(props.endpoints.passkeyCreate)).data;
    });
}

async function remove(key) {
    if (!window.confirm(`Passkey ‘${key.name}’ verwijderen?`)) return;
    await perform(async () => {
        await axios.delete(key.deleteUrl, { data: { password: password.value } });
        keys.value = keys.value.filter(item => item.id !== key.id);
        message.value = 'Passkey verwijderd.';
    });
}
</script>

<template>
    <section class="content-card grid gap-5 p-6 sm:p-8">
        <h2 class="text-2xl">Passkeys beheren</h2>
        <p>Voeg alleen apparaten toe die je zelf beheert. Passkeys kunnen via je wachtwoordmanager naar andere apparaten synchroniseren.</p>
        <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>
        <p v-if="message" role="status">{{ message }}</p>
        <ActionButton :disabled="busy" @click="create">Passkey toevoegen via QR</ActionButton>
        <div v-if="pairing" class="grid gap-4">
            <img :src="pairing.qr.image" alt="Tijdelijke QR-code voor het registreren van een nieuwe passkey" width="320" height="320" class="mx-auto w-full max-w-80 bg-white p-2" />
            <p class="text-center">Registratiecode: <strong class="block text-3xl tracking-widest">{{ pairing.code }}</strong></p>
            <p>Scan met je telefoon. Vul daar deze code en het e-mailadres en wachtwoord van ditzelfde account in. Geldig voor tien minuten.</p>
            <ActionButton :href="pairing.qr.url" variant="outline">Passkey op dit apparaat toevoegen</ActionButton>
        </div>
        <p v-if="!keys.length">Je hebt nog geen passkeys gekoppeld.</p>
        <template v-else>
            <label class="grid max-w-xl gap-2 font-bold">Bevestig je wachtwoord om een passkey te verwijderen
                <input v-model="password" type="password" autocomplete="current-password" class="w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950" />
            </label>
            <ul class="grid gap-4">
                <li v-for="key in keys" :key="key.id" class="grid gap-2 rounded border border-slate-200 p-4">
                    <strong>{{ key.name }}</strong>
                    <p>Aangemaakt: {{ new Date(key.createdAt).toLocaleString('nl-NL') }}</p>
                    <p>Laatst gebruikt: {{ key.lastUsedAt ? new Date(key.lastUsedAt).toLocaleString('nl-NL') : 'Nog niet gebruikt' }}</p>
                    <ActionButton variant="outline" :disabled="busy || !password" @click="remove(key)">Verwijderen</ActionButton>
                </li>
            </ul>
        </template>
        <ActionButton :href="endpoints.passkeys" variant="outline">Overzicht vernieuwen</ActionButton>
        <ActionButton :href="endpoints.account" variant="outline">Terug naar mijn account</ActionButton>
    </section>
</template>
