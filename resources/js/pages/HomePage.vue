<script setup>
import HeroSection from '../components/home/HeroSection.vue';
import EventHighlights from '../components/home/EventHighlights.vue';
import HomeContentGrid from '../components/home/HomeContentGrid.vue';
import LivestreamCard from '../components/home/LivestreamCard.vue';
import DonationCard from '../components/home/DonationCard.vue';
import ChallengesCard from '../components/home/ChallengesCard.vue';
import ProgramCard from '../components/home/ProgramCard.vue';
import SponsorsSection from '../components/home/SponsorsSection.vue';

defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});
</script>

<template>
    <HeroSection :event="event" :links="links" />
    <EventHighlights :items="event.highlights" />
    <div class="home-content page-container">
        <HomeContentGrid>
            <LivestreamCard :stream="event.stream" :image="event.images.livestream" :starts-at="event.startsAt" />
            <DonationCard :amounts="event.donationAmounts" :foundation="event.foundation" />
            <ChallengesCard :challenges="event.challenges" :raised-cents="event.fundraising.raisedCents" :is-preview="event.fundraising.isPreview" :href="links.challenges" />
            <ProgramCard :items="event.schedule" :href="links.program" />
        </HomeContentGrid>
        <SponsorsSection :tiers="event.sponsorTiers" :foundation-name="event.foundation.name" :benefits="event.sponsorBenefits" :href="links.sponsors" />
    </div>
</template>
