<script setup>
import SponsorTier from './SponsorTier.vue';
import ActionButton from '../ui/ActionButton.vue';
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    tiers: { type: Array, required: true },
    foundationName: { type: String, required: true },
    benefits: { type: Array, required: true },
    href: { type: String, required: true },
});
</script>

<template>
    <section id="sponsoren" class="sponsors-section" aria-labelledby="sponsors-title">
        <div class="sponsors-introduction"><p class="section-label">Onze sponsoren</p><h2 id="sponsors-title">Samen maken<br>we dit mogelijk</h2><p>Bedrijven, organisaties en ondernemers uit Emmen en omgeving steunen de 24 Uur Live en helpen {{ foundationName }}.</p></div>
        <div class="sponsor-tiers"><SponsorTier v-for="tier in tiers" :key="tier.id" :tier="tier" /></div>
        <div class="sponsors-action"><ActionButton :href="href" variant="outline" arrow>Word sponsor</ActionButton><ul><li v-for="benefit in benefits" :key="benefit"><AppIcon name="check" />{{ benefit }}</li></ul></div>
    </section>
</template>
