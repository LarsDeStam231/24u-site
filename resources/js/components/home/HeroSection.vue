<script setup>
import EventDetails from './EventDetails.vue';
import DonationCounter from './DonationCounter.vue';
import FoundationLogo from './FoundationLogo.vue';
import ActionButton from '../ui/ActionButton.vue';

defineProps({
    event: { type: Object, required: true },
    links: { type: Object, required: true },
});
</script>

<template>
    <section class="hero" aria-labelledby="event-title">
        <img class="hero-backdrop" :src="event.images.hero" alt="" decoding="async" fetchpriority="high">
        <div class="page-container hero-grid">
            <div class="hero-message">
                <p class="eyebrow">{{ event.name }} {{ event.year }}</p>
                <h1 id="event-title">{{ event.title[0] }}<br><span>{{ event.title[1] }}</span></h1>
                <p class="hero-introduction">{{ event.introduction }}</p>
                <EventDetails :starts-at="event.startsAt" :ends-at="event.endsAt" :location="event.location" />
            </div>
            <div class="hero-fundraising">
                <DonationCounter :fundraising="event.fundraising" :challenges="event.challenges" />
            </div>
            <FoundationLogo :foundation="event.foundation" :image="event.images.foundation" class="hero-foundation" />
            <div class="hero-actions">
                <ActionButton :href="links.donations" icon="heart">Doneer nu</ActionButton>
                <ActionButton :href="links.livestream" variant="light-outline" icon="play">Bekijk de livestream</ActionButton>
                <ActionButton :href="links.sponsors" variant="white" icon="handshake">Sponsor de 24 Uur Live</ActionButton>
            </div>
        </div>
    </section>
</template>
