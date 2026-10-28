<script setup>
defineProps({
    foundation: { type: Object, required: true },
    image: { type: String, required: true },
});
</script>

<template>
    <a class="foundation-logo" :href="foundation.url" target="_blank" rel="noopener noreferrer" :aria-label="`${foundation.name} (opent in nieuw tabblad)`">
        <img :src="image" :alt="`${foundation.name} — Samen wensen mogelijk maken!`" width="617" height="299" decoding="async">
    </a>
</template>
