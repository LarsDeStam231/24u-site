<script setup>
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    image: { type: String, required: true },
    href: { type: String, required: true },
});
</script>

<template>
    <a class="site-brand" :href="href" aria-label="DCTerra 24 Uur Live, naar de homepage">
        <span class="brand-wordmark"><img class="brand-logo" :src="image" alt="DCTerra" decoding="async"><span>24 UUR LIVE</span></span>
        <AppIcon name="game" />
        <span class="brand-tagline">SAMEN STERKER</span>
    </a>
</template>
