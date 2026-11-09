<script setup>
import { computed, ref, watch } from 'vue';
import QrCards from '../ui/QrCards.vue';
import SectionTabs from '../ui/SectionTabs.vue';

const props = defineProps({ codes: { type: Object, required: true } });
const labels = { donation: 'Mijn donatiepagina', website: 'Website', foundationDonation: 'Stichting', attendance: 'Aanwezigheid' };
const items = computed(() => Object.keys(labels).filter(id => props.codes[id]).map(id => ({ id, label: labels[id] })));
const activeCode = ref('');
watch(items, value => {
    if (!value.some(item => item.id === activeCode.value)) activeCode.value = value[0]?.id || '';
}, { immediate: true });
</script>

<template>
    <section class="account-card account-qr-selector grid min-w-0 content-start gap-5" aria-labelledby="account-qr-title">
        <header class="grid gap-1">
            <p class="account-eyebrow">Scannen en delen</p>
            <h2 id="account-qr-title">Jouw QR-codes</h2>
            <p class="account-muted">Kies de code die je wilt bekijken of downloaden.</p>
        </header>
        <template v-if="items.length">
            <SectionTabs id="account-qr" v-model="activeCode" :items="items" label="QR-code kiezen" />
            <div v-for="item in items" :id="`account-qr-panel-${item.id}`" :key="item.id" role="tabpanel" :aria-labelledby="`account-qr-tab-${item.id}`" :hidden="activeCode !== item.id">
                <template v-if="activeCode === item.id">
                    <p v-if="item.id === 'attendance'" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">Deze aanwezigheids-QR is privé. Deel deze niet openbaar; laat hem alleen door de organisatie scannen.</p>
                    <QrCards :codes="{ [item.id]: codes[item.id] }" />
                </template>
            </div>
        </template>
        <p v-else class="account-empty-state">Activeer je QR-codes via de link in je e-mail.</p>
    </section>
</template>
