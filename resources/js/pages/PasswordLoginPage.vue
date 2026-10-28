<script setup>
import { ref } from 'vue';
import axios from 'axios';
import PageHeading from '../components/layout/PageHeading.vue';
import ActionButton from '../components/ui/ActionButton.vue';

const props = defineProps({
    page: { type: Object, required: true },
    event: { type: Object, required: true },
    links: { type: Object, required: true },
});

const email = ref('');
const password = ref('');
const busy = ref(false);
const error = ref('');

async function login() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';

    try {
        const response = await axios.post(props.page.submitUrl, { email: email.value, password: password.value });
        password.value = '';
        window.location.assign(response.data.redirect);
    } catch (failure) {
        password.value = '';
        const errors = failure.response?.data?.errors;
        error.value = errors?.email?.[0] || errors?.password?.[0] || failure.response?.data?.message
            || 'Inloggen is niet gelukt. Probeer het opnieuw.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container py-10">
            <section class="content-card mx-auto grid max-w-xl gap-6 p-6 sm:p-8">
                <h2 class="text-2xl">Inloggen</h2>
                <form class="grid gap-5" :aria-busy="busy" @submit.prevent="login">
                    <label for="login-email" class="grid gap-2 font-bold">
                        E-mailadres
                        <input id="login-email" v-model="email" name="email" type="email" required maxlength="255" autocomplete="username" :disabled="busy" class="w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950" />
                    </label>
                    <label for="login-password" class="grid gap-2 font-bold">
                        Wachtwoord
                        <input id="login-password" v-model="password" name="password" type="password" required maxlength="4096" autocomplete="current-password" :disabled="busy" class="w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950" />
                    </label>
                    <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>
                    <ActionButton type="submit" :disabled="busy">{{ busy ? 'Inloggen…' : 'Inloggen' }}</ActionButton>
                </form>
                <p class="text-sm text-slate-600">Je account moet goedgekeurd zijn en een ingesteld wachtwoord hebben. Een registratieaanvraag met alleen een e-mailadres heeft nog geen wachtwoord.</p>
                <ActionButton :href="links.login" variant="outline">Liever inloggen met een QR-code</ActionButton>
                <p>Nog geen account? <a :href="links.registration" class="font-bold underline">Registreren</a></p>
            </section>
        </div>
    </div>
</template>
