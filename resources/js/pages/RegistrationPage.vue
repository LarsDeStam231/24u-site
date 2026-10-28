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
const busy = ref(false);
const submitted = ref(false);
const message = ref('');
const error = ref('');
const emailError = ref('');

async function register() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    emailError.value = '';

    try {
        const response = await axios.post(props.page.submitUrl, { email: email.value });
        message.value = response.data.message;
        submitted.value = true;
    } catch (failure) {
        emailError.value = failure.response?.data?.errors?.email?.[0] || '';
        if (!emailError.value) {
            error.value = failure.response?.data?.message || 'Je aanmelding kon niet worden verstuurd. Probeer het opnieuw.';
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container py-10">
            <section class="content-card mx-auto grid max-w-2xl gap-6 p-6 sm:p-8">
                <template v-if="submitted">
                    <div role="status" class="grid gap-3 rounded border border-green-300 bg-green-50 p-5 text-green-950">
                        <h2 class="text-2xl">Je aanmelding is ontvangen</h2>
                        <p>{{ message }}</p>
                    </div>
                    <p>Je bent nog niet ingelogd. Na goedkeuring krijg je een e-mail met een activatielink om je account en persoonlijke QR-codes te activeren.</p>
                    <ActionButton :href="links.login" variant="outline">Naar inloggen</ActionButton>
                </template>
                <template v-else>
                    <h2 class="text-2xl">Vraag jouw account aan</h2>
                    <p>Vul je e-mailadres in om je aan te melden. Ben je student? Gebruik dan je schoolmailadres.</p>
                    <form class="grid gap-5" :aria-busy="busy" @submit.prevent="register">
                        <label class="grid gap-2 font-bold" for="registration-email">
                            E-mailadres
                            <input id="registration-email" v-model="email" name="email" type="email" required maxlength="255" autocomplete="email" :disabled="busy" :aria-invalid="Boolean(emailError)" :aria-describedby="emailError ? 'registration-error' : 'registration-help'" class="w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950" />
                        </label>
                        <p id="registration-help" class="text-sm text-slate-600">Je hoeft nu nog geen wachtwoord in te stellen of QR-code te hebben.</p>
                        <p v-if="emailError || error" id="registration-error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ emailError || error }}</p>
                        <ActionButton type="submit" :disabled="busy">{{ busy ? 'Aanmelding versturen…' : 'Aanmelding versturen' }}</ActionButton>
                    </form>
                    <div class="grid gap-2 rounded bg-slate-100 p-4">
                        <h3 class="font-bold">Wat gebeurt er na je aanmelding?</h3>
                        <ol class="grid list-inside list-decimal gap-2">
                            <li>Een beheerder controleert je aanvraag en wijst je een rol toe.</li>
                            <li>Na goedkeuring ontvang je een activatielink per e-mail.</li>
                            <li>Je activeert je QR-codes en kunt daarna inloggen.</li>
                        </ol>
                    </div>
                    <p>Heb je al een account? <a :href="links.login" class="font-bold underline">Inloggen</a></p>
                </template>
            </section>
        </div>
    </div>
</template>
