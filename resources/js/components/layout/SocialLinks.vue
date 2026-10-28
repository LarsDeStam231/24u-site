<script setup>
import AppIcon from '../ui/AppIcon.vue';

defineProps({ links: { type: Array, required: true } });
</script>

<template>
    <div class="social-links">
        <a v-for="link in links" :key="link.label" :href="link.href" :aria-label="`${link.label} (opent in nieuw tabblad)`" target="_blank" rel="noopener noreferrer">
            <AppIcon :name="link.icon" />
        </a>
    </div>
</template>
