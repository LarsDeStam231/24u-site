<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import SponsorsSection from '../components/home/SponsorsSection.vue';
import SponsorTier from '../components/home/SponsorTier.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import AppIcon from '../components/ui/AppIcon.vue';

defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});
</script>

<template>
    <PageHeading :page="page" :event="event" />
    <div class="page-container detail-content">
        <SponsorsSection :tiers="event.sponsorTiers" :foundation-name="event.foundation.name" :benefits="event.sponsorBenefits" :href="`${links.sponsors}#sponsor-worden`" />
        <section id="sponsor-worden" class="content-card prose-card" aria-labelledby="become-sponsor-title">
            <p class="section-label">Samen sterker</p>
            <h2 id="become-sponsor-title">Word sponsor van {{ event.name }}</h2>
            <p>Help mee als bedrijf of organisatie. Neem contact op met de organisatie om de sponsormogelijkheden te bespreken.</p>
            <ul class="sponsor-benefits"><li v-for="benefit in event.sponsorBenefits" :key="benefit"><AppIcon name="check" />{{ benefit }}</li></ul>
            <ActionButton :href="`mailto:${event.email}?subject=${encodeURIComponent(`Sponsor worden — ${event.name}`)}`" icon="mail">Neem contact op</ActionButton>
        </section>
    </div>
</template>
