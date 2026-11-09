<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ActionButton from '../ui/ActionButton.vue';
import { createPersonalFlyer } from '../../utils/personalFlyer';

const props = defineProps({ flyer: { type: Object, default: null } });
const previewUrl = ref('');
const loading = ref(false);
const error = ref('');
let generation = 0;

function releasePreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
}

async function generate() {
    const currentGeneration = ++generation;
    releasePreview();
    error.value = '';
    loading.value = Boolean(props.flyer);
    if (!props.flyer) return;

    try {
        const blob = await createPersonalFlyer(props.flyer.templateUrl, props.flyer.donationCode.image);
        if (currentGeneration === generation) previewUrl.value = URL.createObjectURL(blob);
    } catch (failure) {
        if (currentGeneration === generation) error.value = failure.message || 'De flyer kon niet worden gemaakt.';
    } finally {
        if (currentGeneration === generation) loading.value = false;
    }
}

onMounted(generate);
watch(() => props.flyer, generate);
onBeforeUnmount(() => { generation++; releasePreview(); });
</script>

<template>
    <section class="account-card grid min-w-0 content-start gap-5" aria-labelledby="personal-flyer-title" :aria-busy="loading">
        <header class="grid gap-1">
            <p class="account-eyebrow">Klaar om te delen</p>
            <h2 id="personal-flyer-title">Jouw persoonlijke flyer</h2>
            <p class="account-muted">Het originele ontwerp, met jouw eigen donatie-QR rechtsonder.</p>
        </header>
        <template v-if="flyer">
            <img v-if="previewUrl" :src="previewUrl" alt="DCTerra 24 Uur Live-flyer met mijn persoonlijke donatie-QR rechtsonder" width="1024" height="1536" class="mx-auto w-full max-w-80 rounded-lg border border-slate-200" />
            <div v-else class="account-empty-state" :role="error ? 'alert' : 'status'">
                <p>{{ error || 'Je persoonlijke flyer wordt gemaakt…' }}</p>
                <ActionButton v-if="error" variant="outline" @click="generate">Opnieuw proberen</ActionButton>
            </div>
            <div v-if="previewUrl" class="flex flex-wrap gap-2">
                <ActionButton :href="previewUrl" download="24uur-live-persoonlijke-flyer.png">Download persoonlijke flyer</ActionButton>
                <ActionButton :href="flyer.donationCode.url" variant="outline">Bekijk mijn donatiepagina</ActionButton>
            </div>
            <p class="account-muted text-xs">Alleen je openbare donatiepagina staat op de flyer. Je accountgegevens en aanwezigheids-QR worden niet gedeeld.</p>
        </template>
        <div v-else class="account-empty-state">
            <p class="font-semibold text-slate-800">Nog geen persoonlijke flyer beschikbaar</p>
            <p>Je flyer verschijnt zodra je studentaccount een openbare naam en een geactiveerde persoonlijke donatiepagina heeft.</p>
        </div>
    </section>
</template>
