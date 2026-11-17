<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import ActionButton from '../ui/ActionButton.vue';
import { formatDate, formatMoney } from '../../utils/format';

const props = defineProps({ endpoint: { type: String, required: true } });
const donations = ref(null);
const status = ref('');
const busy = ref(false);
const error = ref('');
const statuses = {
    completed: 'Betaald', pending: 'In afwachting', failed: 'Mislukt',
    cancelled: 'Geannuleerd', expired: 'Verlopen', refunded: 'Terugbetaald',
};
const statusStyles = { completed: 'approved', pending: 'pending' };

async function load(url = props.endpoint, params = status.value ? { status: status.value } : {}) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        donations.value = (await axios.get(url, { params })).data;
    } catch (failure) {
        error.value = failure.response?.data?.message || 'De donaties konden niet worden geladen. Probeer het opnieuw.';
    } finally {
        busy.value = false;
    }
}

function selectStatus(value) {
    status.value = value;
    load();
}

onMounted(() => load());
</script>

<template>
    <section class="grid gap-5" aria-labelledby="account-donations-title" :aria-busy="busy">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="grid max-w-xl gap-1">
                <p class="account-eyebrow">Donatiebeheer</p>
                <h2 id="account-donations-title" class="text-xl font-semibold">Donaties</h2>
                <p class="account-muted">Alle donaties via de website, de nieuwste bovenaan. Alleen betaalde donaties tellen mee in de teller.</p>
            </div>
            <label class="account-field grid gap-2" for="account-donation-status">
                Status
                <select id="account-donation-status" :value="status" class="account-input" :disabled="busy" @change="selectStatus($event.target.value)">
                    <option value="">Alle statussen</option>
                    <option v-for="(label, id) in statuses" :key="id" :value="id">{{ label }}</option>
                </select>
            </label>
        </header>

        <p v-if="error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-950">{{ error }}</p>
        <p v-if="!donations && busy" role="status" class="account-muted">Donaties laden…</p>
        <ActionButton v-if="!donations && !busy" variant="outline" @click="load()">Donaties opnieuw laden</ActionButton>

        <template v-if="donations">
            <dl class="account-details account-card grid gap-4 sm:grid-cols-2">
                <div><dt>Totaal betaald</dt><dd>{{ formatMoney(donations.totals.completedCents) }}</dd></div>
                <div><dt>Betaalde donaties</dt><dd>{{ donations.totals.completedCount }}</dd></div>
            </dl>
            <div class="grid gap-4 lg:grid-cols-2">
                <article v-for="donation in donations.data" :key="donation.id" class="account-card grid min-w-0 content-start gap-4">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="grid min-w-0 gap-1">
                            <h3 class="font-semibold">{{ formatMoney(donation.amountCents) }}</h3>
                            <p class="account-muted">{{ formatDate(donation.createdAt, { year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</p>
                        </div>
                        <span class="account-status" :class="`account-status--${statusStyles[donation.status] || 'rejected'}`">{{ statuses[donation.status] || donation.status }}</span>
                    </header>
                    <dl class="account-details grid gap-4 sm:grid-cols-2">
                        <div><dt>Donateur</dt><dd>{{ donation.donorName }}<template v-if="donation.anonymous"> (anoniem op de site)</template></dd></div>
                        <div><dt>E-mailadres</dt><dd class="break-all">{{ donation.donorEmail }}</dd></div>
                        <div><dt>Betaalmethode</dt><dd>{{ donation.paymentMethod || 'Onbekend' }}</dd></div>
                        <div><dt>Via student</dt><dd>{{ donation.studentName || '—' }}</dd></div>
                    </dl>
                </article>
            </div>
            <p v-if="!donations.data.length" class="account-empty-state">Er zijn geen donaties gevonden.</p>
            <nav v-if="donations.prev_page_url || donations.next_page_url" class="flex gap-2" aria-label="Donatiepagina's">
                <ActionButton v-if="donations.prev_page_url" variant="outline" :disabled="busy" @click="load(donations.prev_page_url, {})">Vorige</ActionButton>
                <ActionButton v-if="donations.next_page_url" variant="outline" :disabled="busy" @click="load(donations.next_page_url, {})">Volgende</ActionButton>
            </nav>
        </template>
    </section>
</template>
