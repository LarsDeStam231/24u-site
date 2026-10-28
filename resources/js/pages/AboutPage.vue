<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import FoundationLogo from '../components/home/FoundationLogo.vue';
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
    <div class="page-container detail-content detail-columns">
        <section class="content-card prose-card">
            <p class="section-label">Het evenement</p>
            <h2>{{ event.name }}</h2>
            <p>{{ event.introduction }}</p>
            <EventDetails :starts-at="event.startsAt" :ends-at="event.endsAt" :location="event.location" />
            <ActionButton :href="links.program" variant="outline" icon="calendar">Bekijk het programma</ActionButton>
            <ActionButton :href="`mailto:${event.email}`" variant="outline" icon="mail">Contact met de organisatie</ActionButton>
        </section>
        <section class="content-card prose-card">
            <FoundationLogo :foundation="event.foundation" :image="event.images.foundation" />
            <p class="section-label">Het goede doel</p>
            <h2>{{ event.foundation.name }}</h2>
            <p>{{ event.foundation.description }}</p>
            <ActionButton :href="event.foundation.url" variant="outline" icon="external" target="_blank" rel="noopener noreferrer">Bezoek de stichting</ActionButton>
            <ActionButton :href="links.donations" icon="heart">Help wensen mogelijk maken</ActionButton>
        </section>
    </div>
</template>
