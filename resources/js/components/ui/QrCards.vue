<script setup>
import ActionButton from './ActionButton.vue';

defineProps({ codes: { type: Object, required: true } });

const labels = {
    attendance: 'Aanwezigheid', donation: 'Mijn donatiepagina',
    website: 'Bezoek onze website', foundationDonation: 'Doneer aan Wensstichting Drenthe',
};
const descriptions = {
    attendance: 'Laat deze code bij aankomst en vertrek scannen door de organisatie.',
    donation: 'Deel deze code om mensen naar jouw persoonlijke donatiepagina te sturen.',
    website: 'Scan deze QR-code om onze evenementwebsite te openen.',
    foundationDonation: 'Scan deze QR-code om rechtstreeks via de donatiepagina van Wensstichting Drenthe te doneren.',
};
const linkLabels = {
    donation: 'Bekijk mijn pagina', website: 'Open de website', foundationDonation: 'Open de donatiepagina',
};
</script>

<template>
    <div class="grid gap-6" :class="{ 'md:grid-cols-2': Object.keys(codes).length > 1, 'xl:grid-cols-3': Object.keys(codes).length > 2 }">
        <section v-for="(code, type) in codes" :key="type" class="content-card grid min-w-0 content-start gap-4 p-6">
            <h2 class="text-xl">{{ labels[type] }}</h2>
            <p>{{ descriptions[type] }}</p>
            <img :src="code.image" :alt="`QR-code: ${labels[type]}`" width="320" height="320" class="mx-auto w-full max-w-80 bg-white p-2" />
            <a :href="code.image" :download="`24u-${type}.svg`" :aria-label="`Download QR-code: ${labels[type]}`" class="action-button action-button--primary">Download QR-code</a>
            <label class="grid gap-2 text-sm font-bold">
                Bijbehorende link
                <input :value="code.url" readonly class="min-w-0 w-full rounded border border-slate-300 bg-white p-3 font-normal" type="text" @focus="$event.target.select()" />
            </label>
            <ActionButton v-if="linkLabels[type]" :href="code.url" variant="outline" :rel="type === 'foundationDonation' ? 'noopener noreferrer' : undefined">{{ linkLabels[type] }}</ActionButton>
        </section>
    </div>
</template>
