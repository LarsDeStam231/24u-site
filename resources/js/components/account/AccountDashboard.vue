<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import ActionButton from '../ui/ActionButton.vue';
import AppIcon from '../ui/AppIcon.vue';
import SectionTabs from '../ui/SectionTabs.vue';
import ContentManagement from '../ContentManagement.vue';
import DonationCounter from '../home/DonationCounter.vue';
import AccountRegistrations from './AccountRegistrations.vue';
import AccountQrCodes from './AccountQrCodes.vue';
import PersonalFlyer from './PersonalFlyer.vue';

const props = defineProps({ qr: { type: Object, required: true } });
const activeSection = ref('general');
const visitedSections = ref(['general']);
const busy = ref(false);
const error = ref('');
const roles = { student: 'Student', organisatie: 'Organisatie', vrijwilliger: 'Vrijwilliger', donateur: 'Donateur', gast: 'Gast', admin: 'Beheerder' };
const sections = computed(() => [
    { id: 'general', label: 'Algemeen' },
    ...(props.qr.canManage ? [{ id: 'registrations', label: 'Registratie' }] : []),
    ...(props.qr.canManageContent ? [{ id: 'website', label: 'Websitebeheer' }] : []),
]);
const qrCodes = computed(() => ({
    ...props.qr.shareableCodes, ...props.qr.codes,
    ...(props.qr.flyer ? { donation: props.qr.flyer.donationCode } : {}),
}));

function selectSection(section) {
    if (!sections.value.some(item => item.id === section)) return;
    activeSection.value = section;
    if (!visitedSections.value.includes(section)) visitedSections.value.push(section);
}

async function logout() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const response = await axios.post(props.qr.endpoints.logout);
        window.location.assign(response.data.redirect);
    } catch (failure) {
        error.value = failure.response?.data?.message || 'Uitloggen is niet gelukt. Probeer het opnieuw.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="account-dashboard mx-auto grid w-full max-w-7xl gap-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-1">
                <p class="account-eyebrow">DCTerra 24 Uur Live</p>
                <h1 class="account-title">Mijn account</h1>
                <p class="account-muted">Welkom, {{ qr.account.name || 'deelnemer' }}. Alles voor jouw deelname op één plek.</p>
            </div>
            <ActionButton variant="outline" :disabled="busy" @click="logout">Uitloggen</ActionButton>
        </header>
        <p v-if="error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-950">{{ error }}</p>
        <SectionTabs id="account" :model-value="activeSection" :items="sections" label="Accountonderdelen" @update:model-value="selectSection" />

        <section id="account-panel-general" role="tabpanel" aria-labelledby="account-tab-general" :hidden="activeSection !== 'general'" class="grid gap-5">
            <div class="grid gap-5 lg:grid-cols-2">
                <section class="account-card grid min-w-0 content-start gap-5" aria-labelledby="account-profile-title">
                    <header class="flex items-start justify-between gap-3">
                        <div class="grid gap-1"><p class="account-eyebrow">Algemeen</p><h2 id="account-profile-title">Jouw gegevens</h2></div>
                        <span class="account-status account-status--neutral">{{ roles[qr.account.role] || qr.account.role }}</span>
                    </header>
                    <dl class="account-details grid gap-4 sm:grid-cols-2">
                        <div><dt>Naam</dt><dd>{{ qr.account.name || 'Nog niet ingevuld' }}</dd></div>
                        <div><dt>E-mailadres</dt><dd class="break-all">{{ qr.account.email }}</dd></div>
                        <div v-if="qr.account.studentNumber"><dt>Studentnummer</dt><dd>{{ qr.account.studentNumber }}</dd></div>
                        <div><dt>Aanwezigheid</dt><dd>{{ qr.account.isPresent ? 'Ingecheckt' : 'Niet ingecheckt' }}</dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <ActionButton :href="qr.endpoints.passkeys" variant="outline" icon="lock">Online sleutels beheren</ActionButton>
                        <ActionButton v-if="qr.canScan" :href="qr.endpoints.attendance" variant="outline">Aanwezigheid registreren</ActionButton>
                    </div>
                </section>
                <section v-if="qr.personalFundraising" class="grid min-w-0 content-start gap-4" aria-label="Persoonlijke donatieteller">
                    <DonationCounter :fundraising="qr.personalFundraising" :challenges="[]" :endpoint="qr.endpoints.personalDonationTotal" title="Persoonlijke donatieteller" :show-next-challenge="false" show-goal-status />
                    <p class="account-muted">Alleen bevestigde, aan jou gekoppelde donaties tellen mee. Donaties via de stichting worden nog niet automatisch verwerkt.</p>
                    <ActionButton v-if="qr.flyer" :href="qr.flyer.donationCode.url" variant="outline" class="justify-self-start">Bekijk mijn donatiepagina</ActionButton>
                </section>
                <section v-else class="account-card grid min-w-0 content-start gap-4" aria-labelledby="personal-donation-title">
                    <header class="flex items-start justify-between gap-3">
                        <div class="grid gap-1"><p class="account-eyebrow">Jouw bijdrage</p><h2 id="personal-donation-title">Persoonlijke donatieteller</h2></div>
                        <AppIcon name="heart" class="text-rose-500" />
                    </header>
                    <p class="text-4xl font-semibold tracking-tight text-slate-300" aria-label="Nog geen bevestigde donatiegegevens">—</p>
                    <p class="font-semibold text-slate-700">Nog geen bevestigde bedragen beschikbaar</p>
                    <p class="account-muted">Donaties gaan rechtstreeks naar Wensstichting Drenthe. Zonder betaalbevestigingen kunnen we nog geen persoonlijke bedragen of ranking tonen.</p>
                    <ActionButton v-if="qr.flyer" :href="qr.flyer.donationCode.url" variant="outline" class="justify-self-start">Bekijk mijn donatiepagina</ActionButton>
                </section>
            </div>
            <div class="grid items-start gap-5 lg:grid-cols-2">
                <AccountQrCodes :codes="qrCodes" />
                <PersonalFlyer :flyer="qr.flyer" />
            </div>
            <details v-if="qr.history?.length" class="account-card">
                <summary class="cursor-pointer font-semibold">Mijn aanwezigheidsgeschiedenis</summary>
                <ul class="mt-4 grid gap-2 text-sm text-slate-600"><li v-for="(entry, index) in qr.history" :key="index">{{ entry.created_at }} — {{ entry.action === 'check-in' ? 'Ingecheckt' : 'Uitgecheckt' }}</li></ul>
            </details>
        </section>

        <section v-if="qr.canManage" id="account-panel-registrations" role="tabpanel" aria-labelledby="account-tab-registrations" :hidden="activeSection !== 'registrations'">
            <AccountRegistrations v-if="visitedSections.includes('registrations')" :endpoints="qr.endpoints" />
        </section>
        <section v-if="qr.canManageContent" id="account-panel-website" role="tabpanel" aria-labelledby="account-tab-website" :hidden="activeSection !== 'website'">
            <ContentManagement v-if="visitedSections.includes('website')" :endpoints="qr.endpoints" :can-manage-challenge-ladder="qr.canManageChallengeLadder" />
        </section>
    </div>
</template>
