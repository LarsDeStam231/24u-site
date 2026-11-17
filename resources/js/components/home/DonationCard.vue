<script setup>
import ActionButton from '../ui/ActionButton.vue';
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    foundation: { type: Object, required: true },
    donatePage: { type: String, default: null },
});
</script>

<template>
    <section id="doneren" class="content-card donation-card" aria-labelledby="donation-title">
        <p class="section-label">Doneren</p>
        <h2 id="donation-title">Help wensen mogelijk maken!</h2>
        <p class="card-introduction">Jouw donatie helpt {{ foundation.name }} om bijzondere wensen waar te maken. Ieder bedrag maakt verschil.</p>
        <template v-if="donatePage">
            <div class="donation-action">
                <p>Kies je bedrag en betaal veilig online. Je donatie telt direct mee in de tussenstand.</p>
                <ActionButton :href="donatePage" icon="heart">Doneer nu</ActionButton>
            </div>
            <p class="donation-security"><AppIcon name="shield" />Veilig betalen via Mollie.</p>
        </template>
        <template v-else>
            <div class="donation-action">
                <p>Kies je bedrag en rond je donatie af op de officiële website van {{ foundation.name }}.</p>
                <ActionButton :href="foundation.donateUrl" icon="heart" target="_blank" rel="noopener noreferrer">Doneer nu</ActionButton>
            </div>
            <p class="donation-security"><AppIcon name="shield" />Doneren via de officiële stichting.</p>
            <p class="donation-note">Donaties via de stichting worden niet automatisch in deze teller verwerkt.</p>
        </template>
    </section>
</template>
