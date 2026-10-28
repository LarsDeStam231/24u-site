<script setup>
import { ref } from 'vue';
import axios from 'axios';
import PageHeading from '../components/layout/PageHeading.vue';
import ActionButton from '../components/ui/ActionButton.vue';

const props = defineProps({ page: { type: Object, required: true }, event: { type: Object, required: true }, links: { type: Object, required: true } });
const fragment = new URLSearchParams(window.location.hash.slice(1));
const token = ref(fragment.get('token') || '');
const email = ref(fragment.get('email') || '');
if (window.location.hash) window.history.replaceState(null, '', window.location.pathname);
const name = ref('');
const password = ref('');
const confirmation = ref('');
const error = ref('');
const busy = ref(false);
const inputClass = 'w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950';

async function save() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const response = await axios.post(props.page.submitUrl, { email: email.value, token: token.value, name: name.value, password: password.value, password_confirmation: confirmation.value });
        token.value = '';
        window.location.assign(response.data.redirect);
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0] || failure.response?.data?.message || 'Dit is niet gelukt. Probeer opnieuw.';
    } finally {
        password.value = '';
        confirmation.value = '';
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container py-10">
            <section class="content-card mx-auto grid max-w-xl gap-5 p-6 sm:p-8">
                <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ error }}</p>
                <form v-if="token" class="grid gap-5" @submit.prevent="save">
                    <label class="grid gap-2 font-bold">E-mailadres<input v-model="email" :class="inputClass" type="email" required readonly autocomplete="username" /></label>
                    <label class="grid gap-2 font-bold">Openbare naam (bij eerste instelling)<input v-model="name" :class="inputClass" maxlength="120" autocomplete="name" /></label>
                    <label class="grid gap-2 font-bold">Nieuw wachtwoord<input v-model="password" :class="inputClass" type="password" required minlength="12" maxlength="72" autocomplete="new-password" /></label>
                    <label class="grid gap-2 font-bold">Herhaal wachtwoord<input v-model="confirmation" :class="inputClass" type="password" required minlength="12" maxlength="72" autocomplete="new-password" /></label>
                    <p>Gebruik minimaal 12 tekens. Je browser kan aanbieden je wachtwoord veilig te bewaren; de website slaat alleen een hash op.</p>
                    <ActionButton type="submit" :disabled="busy">Wachtwoord opslaan</ActionButton>
                </form>
                <p v-else>Open de volledige link uit je e-mail. Vraag bij een verlopen link een nieuwe e-mail aan via de inlogpagina.</p>
                <ActionButton :href="links.login" variant="outline">Terug naar inloggen</ActionButton>
            </section>
        </div>
    </div>
</template>
