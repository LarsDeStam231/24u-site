<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import { computed } from 'vue';
import DonationCard from '../components/home/DonationCard.vue';
import DonationForm from '../components/home/DonationForm.vue';
import DonationCounter from '../components/home/DonationCounter.vue';
import FoundationLogo from '../components/home/FoundationLogo.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import { formatMoney } from '../utils/format';

const props = defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});

const results = {
    completed: { title: 'Bedankt voor je donatie!', text: amount => `Je betaling van ${amount} is ontvangen en telt mee in de tussenstand.` },
    pending: { title: 'We wachten op je betaling', text: amount => `Je betaling van ${amount} is nog niet bevestigd. Zodra dat gebeurt, telt je donatie automatisch mee.` },
    refunded: { title: 'Je donatie is terugbetaald', text: amount => `De betaling van ${amount} is teruggestort en telt niet mee in de tussenstand.` },
};
const failedResult = { title: 'Je betaling is niet gelukt', text: () => 'Er is niets afgeschreven. Je kunt het hieronder opnieuw proberen.' };
const result = computed(() => {
    const donation = props.page.donationResult;
    if (!donation) return null;
    const { title, text } = results[donation.status] || failedResult;

    return { title, text: text(formatMoney(donation.amountCents)) };
});
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container detail-content detail-columns">
            <div class="detail-stack">
                <section v-if="result" class="content-card donation-result" role="status">
                    <h2>{{ result.title }}</h2>
                    <p>{{ result.text }}</p>
                </section>
                <DonationForm v-if="links.donationStore" :endpoint="links.donationStore" :foundation="event.foundation" :limits="event.donation" />
                <DonationCard v-else :foundation="event.foundation" />
            </div>
            <aside class="detail-stack" aria-label="Het goede doel en de tussenstand">
                <DonationCounter :fundraising="event.fundraising" :challenges="event.challenges" :endpoint="links.donationTotal" />
                <section class="content-card prose-card">
                    <FoundationLogo :foundation="event.foundation" :image="event.images.foundation" />
                    <h2>Voor {{ event.foundation.name }}</h2>
                    <p>{{ event.foundation.description }}</p>
                    <ActionButton :href="links.about" variant="outline" arrow>Meer over het goede doel</ActionButton>
                </section>
            </aside>
        </div>
    </div>
</template>
