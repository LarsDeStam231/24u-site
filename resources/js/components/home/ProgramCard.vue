<script setup>
import ProgramTimeline from './ProgramTimeline.vue';
import ActionButton from '../ui/ActionButton.vue';

defineProps({
    items: { type: Array, required: true },
    href: { type: String, required: true },
});
</script>

<template>
    <section id="programma" class="content-card program-card" aria-labelledby="program-title">
        <p class="section-label">Programma</p>
        <h2 id="program-title">Wat staat er op de planning?</h2>
        <ProgramTimeline :items="items" />
        <ActionButton :href="href" variant="outline" icon="calendar" arrow class="card-bottom-action">Bekijk het volledige programma</ActionButton>
    </section>
</template>
