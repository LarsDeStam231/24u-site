<script setup>
import { computed } from 'vue';
import ProgramTimeline from './ProgramTimeline.vue';
import ActionButton from '../ui/ActionButton.vue';

const props = defineProps({
    items: { type: Array, required: true },
    href: { type: String, required: true },
});

const previewItems = computed(() => props.items.slice(0, 5));
</script>

<template>
    <section id="programma" class="content-card program-card" aria-labelledby="program-title">
        <p class="section-label">Programma</p>
        <h2 id="program-title">Wat staat er op de planning?</h2>
        <ProgramTimeline :items="previewItems" />
        <p v-if="items.length > previewItems.length" class="program-preview-note">{{ items.length - previewItems.length }} extra onderdelen staan in de volledige planning.</p>
        <ActionButton :href="href" variant="outline" icon="calendar" arrow class="card-bottom-action">Bekijk het volledige programma</ActionButton>
    </section>
</template>
