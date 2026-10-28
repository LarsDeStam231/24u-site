<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import DonationCard from '../components/home/DonationCard.vue';
import DonationCounter from '../components/home/DonationCounter.vue';
import FoundationLogo from '../components/home/FoundationLogo.vue';
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
        <DonationCard :amounts="event.donationAmounts" :foundation="event.foundation" />
        <aside class="detail-stack" aria-label="Het goede doel en de tussenstand">
            <DonationCounter :fundraising="event.fundraising" :challenges="event.challenges" />
            <section class="content-card prose-card">
                <FoundationLogo :foundation="event.foundation" :image="event.images.foundation" />
                <h2>Voor {{ event.foundation.name }}</h2>
                <p>{{ event.foundation.description }}</p>
                <ActionButton :href="links.about" variant="outline" arrow>Meer over het goede doel</ActionButton>
            </section>
        </aside>
    </div>
</template>
