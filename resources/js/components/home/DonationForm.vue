<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import ActionButton from '../ui/ActionButton.vue';
import AppIcon from '../ui/AppIcon.vue';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    endpoint: { type: String, required: true },
    foundation: { type: Object, required: true },
    limits: { type: Object, required: true },
    studentToken: { type: String, default: null },
    studentName: { type: String, default: null },
});

const presets = [500, 1000, 2500, 5000];
const presetCents = ref(1000);
const customAmount = ref('');
const name = ref('');
const email = ref('');
const anonymous = ref(false);
const busy = ref(false);
const errors = ref({});
const error = ref('');
const inputClass = 'w-full rounded border border-slate-300 bg-white p-3 font-normal text-slate-950';

const amountCents = computed(() => (String(customAmount.value).trim() === ''
    ? presetCents.value
    : Math.round(Number(String(customAmount.value).trim().replace(',', '.')) * 100)));
const validAmount = computed(() => Number.isInteger(amountCents.value)
    && amountCents.value >= props.limits.minCents && amountCents.value <= props.limits.maxCents);

function choosePreset(cents) {
    presetCents.value = cents;
    customAmount.value = '';
}

async function donate() {
    if (busy.value) return;
    busy.value = true;
    errors.value = {};
    error.value = '';

    try {
        const response = await axios.post(props.endpoint, {
            amountCents: amountCents.value, name: name.value, email: email.value,
            anonymous: anonymous.value, student: props.studentToken,
        });
        window.location.assign(response.data.checkoutUrl);
    } catch (failure) {
        errors.value = failure.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            error.value = failure.response?.status === 429
                ? 'Je hebt te vaak geprobeerd te doneren. Wacht een minuut en probeer het opnieuw.'
                : failure.response?.data?.message || 'De betaling kon niet worden gestart. Probeer het opnieuw.';
        }
        busy.value = false;
    }
}
</script>

<template>
    <section id="doneren" class="content-card donation-card" aria-labelledby="donation-title">
        <p class="section-label">Doneren</p>
        <h2 id="donation-title">{{ studentName ? `Steun de actie van ${studentName}` : 'Help wensen mogelijk maken!' }}</h2>
        <p class="card-introduction">Jouw donatie helpt {{ foundation.name }} om bijzondere wensen waar te maken. Ieder bedrag maakt verschil.</p>
        <form class="grid gap-5" :aria-busy="busy" @submit.prevent="donate">
            <fieldset class="grid gap-3">
                <legend class="font-bold">Kies een bedrag</legend>
                <div class="donation-presets">
                    <button v-for="cents in presets" :key="cents" type="button" class="donation-preset" :aria-pressed="String(customAmount).trim() === '' && presetCents === cents" :disabled="busy" @click="choosePreset(cents)">{{ formatMoney(cents) }}</button>
                </div>
                <label class="grid gap-2 font-bold" for="donation-amount">
                    Of vul zelf een bedrag in (€)
                    <input id="donation-amount" v-model="customAmount" name="amount" type="text" inputmode="decimal" autocomplete="off" placeholder="Bijvoorbeeld 15" :disabled="busy" :aria-invalid="Boolean(errors.amountCents) || !validAmount" aria-describedby="donation-amount-help" :class="inputClass" />
                </label>
                <p id="donation-amount-help" class="donation-note" :class="{ 'form-error': errors.amountCents || !validAmount }" :role="errors.amountCents || !validAmount ? 'alert' : undefined">
                    {{ errors.amountCents?.[0] || `Je kunt tussen ${formatMoney(limits.minCents)} en ${formatMoney(limits.maxCents)} doneren.` }}
                </p>
            </fieldset>
            <label class="grid gap-2 font-bold" for="donation-name">
                Naam
                <input id="donation-name" v-model="name" name="name" type="text" required maxlength="255" autocomplete="name" :disabled="busy" :aria-invalid="Boolean(errors.name)" :class="inputClass" />
                <span v-if="errors.name" role="alert" class="form-error font-normal">{{ errors.name[0] }}</span>
            </label>
            <label class="grid gap-2 font-bold" for="donation-email">
                E-mailadres
                <input id="donation-email" v-model="email" name="email" type="email" required maxlength="255" autocomplete="email" :disabled="busy" :aria-invalid="Boolean(errors.email)" :class="inputClass" />
                <span v-if="errors.email" role="alert" class="form-error font-normal">{{ errors.email[0] }}</span>
            </label>
            <label class="flex items-center gap-3" for="donation-anonymous">
                <input id="donation-anonymous" v-model="anonymous" name="anonymous" type="checkbox" :disabled="busy" />
                Anoniem doneren: toon mijn naam niet
            </label>
            <p v-if="errors.student || error" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-red-950">{{ errors.student?.[0] || error }}</p>
            <ActionButton type="submit" icon="heart" :disabled="busy || !validAmount">{{ busy ? 'Betaling starten…' : `Doneer ${validAmount ? formatMoney(amountCents) : ''}`.trim() }}</ActionButton>
        </form>
        <p class="donation-security"><AppIcon name="shield" />Je betaalt veilig via Mollie. Wij ontvangen geen bank- of kaartgegevens.</p>
        <p class="donation-note">Na je betaling telt je donatie automatisch mee in de tussenstand.</p>
    </section>
</template>
