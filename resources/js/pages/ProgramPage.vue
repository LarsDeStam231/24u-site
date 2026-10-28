<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import ProgramTimeline from '../components/home/ProgramTimeline.vue';
import EventDetails from '../components/home/EventDetails.vue';
import ActionButton from '../components/ui/ActionButton.vue';

defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});
</script>

<template>
    <PageHeading :page="page" :event="event" />
    <div class="page-container detail-content detail-columns detail-columns--wide">
        <section class="content-card full-program" aria-labelledby="schedule-title">
            <p class="section-label">Van aftrap tot finale</p>
            <h2 id="schedule-title">Het programma</h2>
            <ProgramTimeline :items="event.schedule" />
        </section>
        <aside class="content-card prose-card" aria-label="Praktische informatie">
            <h2>Wanneer en waar?</h2>
            <EventDetails :starts-at="event.startsAt" :ends-at="event.endsAt" :location="event.location" />
            <p>Dit is de voorlopige planning. De nieuwste programma-informatie vind je op deze pagina.</p>
            <ActionButton :href="links.livestream" icon="play">Volg de livestream</ActionButton>
        </aside>
    </div>
</template>
