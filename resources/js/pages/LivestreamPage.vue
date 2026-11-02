<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import LivestreamCard from '../components/home/LivestreamCard.vue';
import DonationCounter from '../components/home/DonationCounter.vue';
import CountdownTimer from '../components/home/CountdownTimer.vue';
import EventDetails from '../components/home/EventDetails.vue';
import ActionButton from '../components/ui/ActionButton.vue';

defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container detail-content livestream-page">
            <CountdownTimer :target-date="event.startsAt" :end-date="event.endsAt" :is-live="event.stream.live" />
            <div class="detail-columns detail-columns--wide">
                <LivestreamCard :stream="event.stream" :image="event.images.livestream" :starts-at="event.startsAt" />
                <aside class="detail-stack" aria-label="Over de livestream">
                    <DonationCounter :fundraising="event.fundraising" :challenges="event.challenges" :endpoint="links.donationTotal" />
                    <section class="content-card prose-card">
                        <h2>24 uur samen voor het goede doel</h2>
                        <p>{{ event.introduction }}</p>
                        <EventDetails :starts-at="event.startsAt" :ends-at="event.endsAt" :location="event.location" />
                        <ActionButton :href="links.program" variant="outline" icon="calendar">Bekijk het programma</ActionButton>
                        <ActionButton :href="links.donations" icon="heart">Steun het goede doel</ActionButton>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</template>
