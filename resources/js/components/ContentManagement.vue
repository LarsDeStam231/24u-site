<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import ActionButton from './ui/ActionButton.vue';
import SectionTabs from './ui/SectionTabs.vue';
import { formatMoney } from '../utils/format';

const props = defineProps({
    endpoints: { type: Object, required: true },
    canManageChallengeLadder: { type: Boolean, required: true },
});

const sections = [
    { id: 'program', label: 'Programma' },
    { id: 'challenges', label: 'Challenges' },
    { id: 'sponsors', label: 'Sponsoren' },
];
const companyTypes = [
    { value: '', label: 'Overig' },
    { value: 'School', label: 'School' },
    { value: 'Supermarkt', label: 'Supermarkt' },
    { value: 'Horeca', label: 'Horeca' },
    { value: 'Bakkerij', label: 'Bakkerij' },
];
const sponsorshipTypes = [
    { value: 'gold', label: 'Goud' },
    { value: 'silver', label: 'Zilver' },
    { value: 'bronze', label: 'Brons' },
];

const activeSection = ref('program');
const content = ref({ program: [], challenges: [], sponsors: [] });
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const message = ref('');
const programForm = ref(blankProgramForm());
const challengeForm = ref(blankChallengeForm());
const sponsorForm = ref(blankSponsorForm());

function blankProgramForm() {
    return {
        id: null, date: '', time: '', title: '', description: '', responsible: '', presenter: '',
        production: '', materials: '', donationChallenge: '', sortOrder: 0,
    };
}

function blankChallengeForm() {
    return { id: null, amountEuro: '', name: '', description: '' };
}

function blankSponsorForm() {
    return { id: null, companyName: '', sponsorshipType: 'bronze', companyType: '', logoUrl: null, logo: null, removeLogo: false };
}

const sponsorLogoInput = ref(null);
const sponsorLogoPreview = ref('');
const currentSponsorLogo = computed(() => sponsorLogoPreview.value || (sponsorForm.value.removeLogo ? '' : sponsorForm.value.logoUrl));

async function loadContent() {
    loading.value = true;

    try {
        const response = await axios.get(props.endpoints.content);
        content.value = response.data;
    } catch (failure) {
        error.value = failure.response?.data?.message || 'De beheerinformatie kon niet worden geladen.';
    } finally {
        loading.value = false;
    }
}

async function perform(action) {
    if (busy.value) return false;

    busy.value = true;
    error.value = '';
    message.value = '';

    try {
        const response = await action();
        message.value = response.data.message;
        await loadContent();
        return true;
    } catch (failure) {
        const validationMessage = Object.values(failure.response?.data?.errors || {}).flat()[0];
        error.value = validationMessage || failure.response?.data?.message || 'Opslaan is niet gelukt. Controleer de gegevens en probeer opnieuw.';
        return false;
    } finally {
        busy.value = false;
    }
}

function editProgramItem(item) {
    programForm.value = { ...blankProgramForm(), ...item };
}

function cancelProgramEdit() {
    programForm.value = blankProgramForm();
}

async function saveProgramItem() {
    const item = programForm.value;
    const endpoint = item.id
        ? props.endpoints.programUpdate.replace('__PROGRAM_ITEM__', item.id)
        : props.endpoints.programStore;
    const method = item.id ? 'patch' : 'post';
    const { id, ...data } = item;

    await perform(async () => {
        const response = await axios[method](endpoint, data);
        cancelProgramEdit();
        return response;
    });
}

async function deleteProgramItem(item) {
    if (!window.confirm(`Weet je zeker dat je “${item.title}” wilt verwijderen?`)) return;

    const endpoint = props.endpoints.programDelete.replace('__PROGRAM_ITEM__', item.id);
    const deleted = await perform(() => axios.delete(endpoint));
    if (deleted && programForm.value.id === item.id) cancelProgramEdit();
}

function editChallenge(challenge) {
    challengeForm.value = {
        id: challenge.id, amountEuro: (challenge.amountCents / 100).toFixed(2),
        name: challenge.name, description: challenge.description,
    };
}

function cancelChallengeEdit() {
    challengeForm.value = blankChallengeForm();
}

async function saveChallenge() {
    const challenge = challengeForm.value;
    const endpoint = challenge.id
        ? props.endpoints.challengeUpdate.replace('__CHALLENGE__', challenge.id)
        : props.endpoints.challengeStore;
    const method = challenge.id ? 'patch' : 'post';
    const data = { name: challenge.name, description: challenge.description };

    if (props.canManageChallengeLadder) {
        data.amountCents = Math.round(Number(challenge.amountEuro) * 100);
    }

    await perform(async () => {
        const response = await axios[method](endpoint, data);
        cancelChallengeEdit();
        return response;
    });
}

async function deleteChallenge(challenge) {
    if (!window.confirm(`Weet je zeker dat je “${challenge.name}” wilt verwijderen?`)) return;

    const endpoint = props.endpoints.challengeDelete.replace('__CHALLENGE__', challenge.id);
    const deleted = await perform(() => axios.delete(endpoint));
    if (deleted && challengeForm.value.id === challenge.id) cancelChallengeEdit();
}

function clearSponsorLogoPreview() {
    if (sponsorLogoPreview.value) URL.revokeObjectURL(sponsorLogoPreview.value);
    sponsorLogoPreview.value = '';
    if (sponsorLogoInput.value) sponsorLogoInput.value.value = '';
}

function resetSponsorLogo() {
  clearSponsorLogoPreview();
  if (sponsorLogoInput.value) sponsorLogoInput.value.value = '';
}

function editSponsor(sponsor) {
    resetSponsorLogo();
    sponsorForm.value = { ...blankSponsorForm(), ...sponsor };
}

function cancelSponsorEdit() {
    resetSponsorLogo();
    sponsorForm.value = blankSponsorForm();
}

function selectSponsorLogo(event) {
    const [file] = event.target.files;
    clearSponsorLogoPreview();
    sponsorForm.value.logo = file || null;
    sponsorForm.value.removeLogo = false;
    if (file) sponsorLogoPreview.value = URL.createObjectURL(file);
}

function removeSponsorLogo() {
    resetSponsorLogo();
    sponsorForm.value.logo = null;
    sponsorForm.value.removeLogo = true;
}

async function saveSponsor() {
    const sponsor = sponsorForm.value;
    const endpoint = sponsor.id
        ? props.endpoints.sponsorUpdate.replace('__SPONSOR__', sponsor.id)
        : props.endpoints.sponsorStore;
    const data = new FormData();
    data.append('companyName', sponsor.companyName);
    data.append('sponsorshipType', sponsor.sponsorshipType);
    data.append('companyType', sponsor.companyType || '');
    if (sponsor.logo) data.append('logo', sponsor.logo);
    if (sponsor.removeLogo) data.append('removeLogo', '1');
    // PHP only parses file uploads on POST, so updates use Laravel's method spoofing.
    if (sponsor.id) data.append('_method', 'PATCH');

    await perform(async () => {
        const response = await axios.post(endpoint, data);
        cancelSponsorEdit();
        return response;
    });
}

async function deleteSponsor(sponsor) {
    if (!window.confirm(`Weet je zeker dat je sponsor “${sponsor.companyName}” wilt verwijderen?`)) return;

    const endpoint = props.endpoints.sponsorDelete.replace('__SPONSOR__', sponsor.id);
    const deleted = await perform(() => axios.delete(endpoint));
    if (deleted && sponsorForm.value.id === sponsor.id) cancelSponsorEdit();
}

onMounted(loadContent);
</script>

<template>
    <section class="content-card grid gap-5 p-6" aria-labelledby="content-management-title">
        <header class="grid gap-2">
            <p class="section-label">Beheer</p>
            <h2 id="content-management-title" class="text-2xl">Website-inhoud beheren</h2>
            <p class="text-slate-600">Wijzigingen worden direct zichtbaar op de publieke website.</p>
        </header>

        <p v-if="message" role="status" class="rounded border border-green-300 bg-green-50 p-3 text-green-950">{{ message }}</p>
        <p v-if="error" role="alert" class="rounded border border-red-300 bg-red-50 p-3 text-red-950">{{ error }}</p>

        <nav class="flex flex-wrap gap-2" aria-label="Beheeronderdelen">
            <button v-for="section in sections" :key="section.id" type="button" class="rounded-full border px-4 py-2 font-semibold" :class="activeSection === section.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700'" :aria-pressed="activeSection === section.id" @click="activeSection = section.id">{{ section.label }}</button>
        </nav>

        <p v-if="loading" class="text-slate-600">Beheerinformatie laden…</p>

        <template v-else>
            <div v-if="activeSection === 'program'" class="grid gap-5">
                <form class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4" @submit.prevent="saveProgramItem">
                    <h3 class="text-lg font-semibold">{{ programForm.id ? 'Programmaonderdeel aanpassen' : 'Programmaonderdeel toevoegen' }}</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1">Datum<input v-model="programForm.date" class="w-full rounded border border-slate-300 bg-white p-2" type="date" required /></label>
                        <label class="grid gap-1">Tijd<input v-model="programForm.time" class="w-full rounded border border-slate-300 bg-white p-2" placeholder="10:00–10:30" maxlength="32" required /></label>
                        <label class="grid gap-1">Titel<input v-model="programForm.title" class="w-full rounded border border-slate-300 bg-white p-2" maxlength="255" required /></label>
                        <label class="grid gap-1">Volgorde<input v-model.number="programForm.sortOrder" class="w-full rounded border border-slate-300 bg-white p-2" type="number" min="0" required /></label>
                    </div>
                    <label class="grid gap-1">Inhoud<textarea v-model="programForm.description" class="w-full rounded border border-slate-300 bg-white p-2" rows="3" maxlength="5000" required /></label>
                    <details class="rounded border border-slate-200 bg-white p-3">
                        <summary class="cursor-pointer font-medium">Optionele rol- en productiegegevens</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-1">Student(en) / verantwoordelijke<input v-model="programForm.responsible" class="w-full rounded border border-slate-300 p-2" maxlength="255" /></label>
                            <label class="grid gap-1">Presentator(en)<input v-model="programForm.presenter" class="w-full rounded border border-slate-300 p-2" maxlength="255" /></label>
                            <label class="grid gap-1">OBS / regie / techniek<input v-model="programForm.production" class="w-full rounded border border-slate-300 p-2" maxlength="255" /></label>
                            <label class="grid gap-1">Materialen / gast<input v-model="programForm.materials" class="w-full rounded border border-slate-300 p-2" maxlength="255" /></label>
                            <label class="grid gap-1 sm:col-span-2">Donatie / challenge<input v-model="programForm.donationChallenge" class="w-full rounded border border-slate-300 p-2" maxlength="255" /></label>
                        </div>
                    </details>
                    <div class="flex flex-wrap gap-2">
                        <ActionButton type="submit" :disabled="busy">{{ programForm.id ? 'Wijzigingen opslaan' : 'Onderdeel toevoegen' }}</ActionButton>
                        <ActionButton v-if="programForm.id" variant="outline" :disabled="busy" @click="cancelProgramEdit">Annuleren</ActionButton>
                    </div>
                </form>

                <div class="grid gap-3">
                    <article v-for="item in content.program" :key="item.id" class="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div>
                            <p class="font-semibold">{{ item.time }} · {{ item.title }}</p>
                            <p class="text-sm text-slate-600">{{ item.date }} · {{ item.description }}</p>
                            <p v-if="item.presenter" class="mt-1 text-sm text-slate-600">Presentator: {{ item.presenter }}</p>
                        </div>
                        <div class="flex gap-2">
                            <ActionButton variant="outline" :disabled="busy" @click="editProgramItem(item)">Aanpassen</ActionButton>
                            <ActionButton variant="outline" :disabled="busy || content.program.length <= 1" @click="deleteProgramItem(item)">Verwijderen</ActionButton>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else-if="activeSection === 'challenges'" class="grid gap-5">
                <p v-if="!canManageChallengeLadder" class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">Organisatie kan challengenamen en beschrijvingen aanpassen. Bedragen en de ladder zelf zijn alleen door een admin te wijzigen.</p>
                <form v-if="canManageChallengeLadder || challengeForm.id" class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4" @submit.prevent="saveChallenge">
                    <h3 class="text-lg font-semibold">{{ challengeForm.id ? 'Challenge aanpassen' : 'Challenge toevoegen' }}</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label v-if="canManageChallengeLadder" class="grid gap-1">Bedrag in euro<input v-model="challengeForm.amountEuro" class="w-full rounded border border-slate-300 bg-white p-2" type="number" min="0.01" step="0.01" required /></label>
                        <label class="grid gap-1">Naam<input v-model="challengeForm.name" class="w-full rounded border border-slate-300 bg-white p-2" maxlength="255" required /></label>
                    </div>
                    <label class="grid gap-1">Beschrijving<textarea v-model="challengeForm.description" class="w-full rounded border border-slate-300 bg-white p-2" rows="3" maxlength="5000" required /></label>
                    <div class="flex flex-wrap gap-2">
                        <ActionButton type="submit" :disabled="busy">{{ challengeForm.id ? 'Wijzigingen opslaan' : 'Challenge toevoegen' }}</ActionButton>
                        <ActionButton v-if="challengeForm.id" variant="outline" :disabled="busy" @click="cancelChallengeEdit">Annuleren</ActionButton>
                    </div>
                </form>

                <div class="grid gap-3">
                    <article v-for="challenge in content.challenges" :key="challenge.id" class="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div>
                            <p class="font-semibold">{{ formatMoney(challenge.amountCents) }} · {{ challenge.name }}</p>
                            <p class="text-sm text-slate-600">{{ challenge.description }}</p>
                        </div>
                        <div class="flex gap-2">
                            <ActionButton variant="outline" :disabled="busy" @click="editChallenge(challenge)">Aanpassen</ActionButton>
                            <ActionButton v-if="canManageChallengeLadder" variant="outline" :disabled="busy || content.challenges.length <= 1" @click="deleteChallenge(challenge)">Verwijderen</ActionButton>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else class="grid gap-5">
                <form class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4" @submit.prevent="saveSponsor">
                    <h3 class="text-lg font-semibold">{{ sponsorForm.id ? 'Sponsor aanpassen' : 'Sponsor toevoegen' }}</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="grid gap-1">Bedrijfsnaam<input v-model="sponsorForm.companyName" class="w-full rounded border border-slate-300 bg-white p-2" maxlength="255" required /></label>
                        <label class="grid gap-1">Sponsorniveau<select v-model="sponsorForm.sponsorshipType" class="w-full rounded border border-slate-300 bg-white p-2"><option v-for="type in sponsorshipTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></label>
                        <label class="grid gap-1">Type organisatie<select v-model="sponsorForm.companyType" class="w-full rounded border border-slate-300 bg-white p-2"><option v-for="type in companyTypes" :key="type.label" :value="type.value">{{ type.label }}</option></select></label>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <div v-if="currentSponsorLogo" class="flex h-24 w-40 items-center justify-center rounded border border-slate-200 bg-white p-2">
                            <img :src="currentSponsorLogo" :alt="`Logo van ${sponsorForm.companyName || 'de sponsor'}`" class="max-h-full max-w-full object-contain" />
                        </div>
                        <label class="grid gap-1">Logo / foto (JPG, PNG, WEBP of GIF, max. 4 MB)
                            <input ref="sponsorLogoInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full rounded border border-slate-300 bg-white p-2" @change="selectSponsorLogo" />
                        </label>
                        <ActionButton v-if="currentSponsorLogo" variant="outline" :disabled="busy" @click="removeSponsorLogo">Logo verwijderen</ActionButton>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <ActionButton type="submit" :disabled="busy">{{ sponsorForm.id ? 'Wijzigingen opslaan' : 'Sponsor toevoegen' }}</ActionButton>
                        <ActionButton v-if="sponsorForm.id" variant="outline" :disabled="busy" @click="cancelSponsorEdit">Annuleren</ActionButton>
                    </div>
                </form>
                <div v-if="content.sponsors.length" class="grid gap-3">
                    <article v-for="sponsor in content.sponsors" :key="sponsor.id" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-4">
                        <div class="flex items-center gap-3">
                            <img v-if="sponsor.logoUrl" :src="sponsor.logoUrl" :alt="`Logo van ${sponsor.companyName}`" class="h-12 w-20 rounded border border-slate-200 bg-white object-contain p-1" />
                            <div>
                            <p class="font-semibold">{{ sponsor.companyName }}</p>
                            <p class="text-sm text-slate-600">{{ sponsor.sponsorshipType }} · {{ sponsor.companyType || 'Overig' }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <ActionButton variant="outline" :disabled="busy" @click="editSponsor(sponsor)">Aanpassen</ActionButton>
                            <ActionButton variant="outline" :disabled="busy" @click="deleteSponsor(sponsor)">Verwijderen</ActionButton>
                        </div>
                    </article>
                </div>
                <p v-else class="text-slate-600">Er zijn nog geen sponsoren toegevoegd.</p>
            </div>
        </template>
    </section>
</template>
