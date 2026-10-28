<script setup>
import { computed, ref } from 'vue';
import ActionButton from '../ui/ActionButton.vue';
import AppIcon from '../ui/AppIcon.vue';
import EventDialog from '../ui/EventDialog.vue';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    amounts: { type: Array, required: true },
    foundation: { type: Object, required: true },
});

const selectedAmount = ref(props.amounts.includes(25) ? 25 : props.amounts[0] ?? null);
const customAmount = ref('');
const errorMessage = ref('');
const amountCents = computed(() => Math.round(Number(customAmount.value || selectedAmount.value) * 100));

function selectAmount(amount) {
    selectedAmount.value = amount;
    customAmount.value = '';
    errorMessage.value = '';
}

function submitDonation(open) {
    if (!Number.isFinite(amountCents.value) || amountCents.value < 100 || amountCents.value > 1000000) {
        errorMessage.value = 'Kies een bedrag tussen € 1 en € 10.000.';
        return;
    }

    errorMessage.value = '';
    open();
}
</script>

<template>
    <section id="doneren" class="content-card donation-card" aria-labelledby="donation-title">
        <p class="section-label">Doneren</p>
        <h2 id="donation-title">Help wensen mogelijk maken!</h2>
        <p class="card-introduction">Jouw donatie helpt {{ foundation.name }} om bijzondere wensen waar te maken. Ieder bedrag maakt verschil.</p>
        <EventDialog title="Samen wensen mogelijk maken">
            <template #trigger="{ open }">
                <form class="donation-form" @submit.prevent="submitDonation(open)">
                    <div class="donation-options" role="group" aria-label="Kies je donatiebedrag">
                        <button v-for="amount in amounts" :key="amount" type="button" :aria-pressed="selectedAmount === amount && !customAmount" @click="selectAmount(amount)">{{ formatMoney(amount * 100) }}</button>
                    </div>
                    <label for="donation-amount" class="sr-only">Een ander donatiebedrag in euro's</label>
                    <div class="custom-amount"><span aria-hidden="true">€</span><input id="donation-amount" v-model="customAmount" type="number" min="1" max="10000" step="0.01" inputmode="decimal" placeholder="Ander bedrag" :aria-invalid="Boolean(errorMessage)" :aria-describedby="errorMessage ? 'donation-error' : undefined" @input="selectedAmount = null; errorMessage = ''"></div>
                    <p v-if="errorMessage" id="donation-error" class="form-error" role="alert">{{ errorMessage }}</p>
                    <ActionButton type="submit" icon="heart">Doneer nu</ActionButton>
                </form>
            </template>
            <div class="donation-dialog-content">
                <AppIcon name="heart" class="donation-dialog-heart" />
                <p>Wat fijn dat je <strong>{{ formatMoney(amountCents) }}</strong> wilt doneren!</p>
                <p>Je kunt doneren via de officiële website van {{ foundation.name }}. Daar vul je het bedrag opnieuw in en rond je je donatie af.</p>
                <p class="muted">De betaalkoppeling van deze evenementwebsite is nog niet aangesloten. Donaties via de stichting worden hier niet automatisch meegeteld.</p>
                <ActionButton :href="foundation.donateUrl" icon="external" target="_blank" rel="noopener noreferrer">Doneer bij de stichting</ActionButton>
            </div>
        </EventDialog>
        <p class="donation-security"><AppIcon name="shield" />Doneren via de officiële stichting.</p>
        <div class="payment-branding" aria-label="Betaalkoppeling met Mollie is in voorbereiding">
            <span class="mollie-wordmark">mollie</span>
            <div class="payment-methods" aria-hidden="true"><span class="payment-ideal">iDEAL</span><span class="payment-bank">BANK</span><span class="payment-visa">VISA</span><span class="payment-mastercard"><i></i><i></i></span></div>
        </div>
        <p class="payment-caption">Betaalkoppeling in voorbereiding</p>
    </section>
</template>
