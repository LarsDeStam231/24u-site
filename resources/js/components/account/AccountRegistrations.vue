<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import ActionButton from '../ui/ActionButton.vue';

const props = defineProps({ endpoints: { type: Object, required: true } });
const accounts = ref(null);
const selected = ref([]);
const busy = ref(false);
const message = ref('');
const error = ref('');
const roles = [
    { id: 'student', label: 'Student' }, { id: 'organisatie', label: 'Organisatie' },
    { id: 'vrijwilliger', label: 'Vrijwilliger' }, { id: 'donateur', label: 'Donateur' },
    { id: 'gast', label: 'Gast' }, { id: 'admin', label: 'Beheerder' },
];
const statuses = { pending: 'In afwachting', approved: 'Goedgekeurd', rejected: 'Afgewezen' };

async function perform(action) {
    if (busy.value) return;
    busy.value = true;
    message.value = '';
    error.value = '';
    try {
        await action();
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || 'De aanvraag kon niet worden verwerkt. Probeer het opnieuw.';
    } finally {
        busy.value = false;
    }
}

async function loadAccounts(url = props.endpoints.requests) {
    accounts.value = (await axios.get(url)).data;
    selected.value = [];
}

async function manageAccount(account, status) {
    await perform(async () => {
        const response = status
            ? await axios.patch(account.approveUrl, { role: account.role, status })
            : await axios.post(account.inviteUrl);
        await loadAccounts();
        message.value = response.data.message;
    });
}

async function inviteSelected() {
    await perform(async () => {
        const response = await axios.post(props.endpoints.inviteSelected, { ids: selected.value });
        message.value = response.data.message;
        selected.value = [];
    });
}

onMounted(() => perform(() => loadAccounts()));
</script>

<template>
    <section class="grid gap-5" aria-labelledby="account-registrations-title" :aria-busy="busy">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid max-w-xl gap-1">
                <p class="account-eyebrow">Deelnemersbeheer</p>
                <h2 id="account-registrations-title" class="text-xl font-semibold">Registraties</h2>
                <p class="account-muted">Beoordeel aanvragen, wijs een rol toe en verstuur de activeringsuitnodiging.</p>
            </div>
            <ActionButton :disabled="busy || !selected.length" @click="inviteSelected">Geselecteerde studenten uitnodigen ({{ selected.length }})</ActionButton>
        </header>

        <p v-if="message" role="status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-950">{{ message }}</p>
        <p v-if="error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-950">{{ error }}</p>
        <p v-if="!accounts && busy" role="status" class="account-muted">Registraties laden…</p>
        <ActionButton v-if="!accounts && !busy" variant="outline" @click="perform(() => loadAccounts())">Registraties opnieuw laden</ActionButton>

        <template v-if="accounts">
            <div class="grid gap-4 lg:grid-cols-2">
                <article v-for="account in accounts.data" :key="account.id" class="account-card grid min-w-0 content-start gap-5">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="grid min-w-0 gap-1">
                            <h3 class="font-semibold">{{ account.name || 'Nieuwe deelnemer' }}</h3>
                            <p class="account-muted break-all">{{ account.email }}</p>
                        </div>
                        <span class="account-status" :class="`account-status--${account.registration_status}`">{{ statuses[account.registration_status] || account.registration_status }}</span>
                    </header>
                    <label class="account-field grid gap-2" :for="`account-role-${account.id}`">
                        Accountrol
                        <select :id="`account-role-${account.id}`" v-model="account.role" class="account-input" :disabled="busy">
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option>
                        </select>
                    </label>
                    <label v-if="account.role === 'student' && account.registration_status === 'approved'" class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="selected" type="checkbox" :value="account.id" :disabled="busy" class="size-4 accent-slate-900" /> Selecteren voor uitnodiging
                    </label>
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <ActionButton :disabled="busy" @click="manageAccount(account, 'approved')">Goedkeuren en uitnodigen</ActionButton>
                        <ActionButton variant="outline" :disabled="busy" @click="manageAccount(account, 'rejected')">Afwijzen</ActionButton>
                        <ActionButton v-if="account.registration_status === 'approved'" variant="outline" :disabled="busy" @click="manageAccount(account)">Uitnodiging opnieuw</ActionButton>
                    </div>
                </article>
            </div>
            <p v-if="!accounts.data.length" class="account-empty-state">Er zijn geen registraties gevonden.</p>
            <nav v-if="accounts.prev_page_url || accounts.next_page_url" class="flex gap-2" aria-label="Registratiepagina's">
                <ActionButton v-if="accounts.prev_page_url" variant="outline" :disabled="busy" @click="perform(() => loadAccounts(accounts.prev_page_url))">Vorige</ActionButton>
                <ActionButton v-if="accounts.next_page_url" variant="outline" :disabled="busy" @click="perform(() => loadAccounts(accounts.next_page_url))">Volgende</ActionButton>
            </nav>
        </template>
    </section>
</template>
