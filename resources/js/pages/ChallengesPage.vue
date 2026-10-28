<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import DonationCounter from '../components/home/DonationCounter.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import AppIcon from '../components/ui/AppIcon.vue';
import { formatMoney } from '../utils/format';

defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});

const isUnlocked = (challenge, fundraising) => !fundraising.isPreview && fundraising.raisedCents >= challenge.amountCents;
</script>

<template>
    <PageHeading :page="page" :event="event" />
    <div class="page-container detail-content">
        <p v-if="event.fundraising.isPreview" class="preview-notice">De teller toont een voorbeeldstand. Hieronder zie je de geplande challenge-mijlpalen.</p>
        <div class="challenge-grid">
            <article v-for="challenge in event.challenges" :key="challenge.amountCents" class="content-card challenge-detail" :class="{ 'challenge-detail--unlocked': isUnlocked(challenge, event.fundraising) }">
                <p class="challenge-status"><AppIcon :name="isUnlocked(challenge, event.fundraising) ? 'check' : 'lock'" />{{ isUnlocked(challenge, event.fundraising) ? 'Vrijgespeeld' : 'Mijlpaal' }}</p>
                <p class="challenge-amount">{{ formatMoney(challenge.amountCents) }}</p>
                <h2>{{ challenge.name }}</h2>
                <p>{{ challenge.description }}</p>
            </article>
        </div>
        <section class="detail-columns">
            <DonationCounter :fundraising="event.fundraising" :challenges="event.challenges" />
            <div class="content-card prose-card">
                <h2>Help de volgende challenge vrijspelen</h2>
                <p>Elke bijdrage aan {{ event.foundation.name }} maakt verschil. Kijk mee tijdens de livestream en moedig de deelnemers aan.</p>
                <ActionButton :href="links.donations" icon="heart">Doneer nu</ActionButton>
                <ActionButton :href="links.livestream" variant="outline" icon="play">Bekijk de livestream</ActionButton>
            </div>
        </section>
    </div>
</template>
