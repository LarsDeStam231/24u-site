<script setup>
defineProps({
    page: { type: Object, required: true },
    event: { type: Object, required: true },
});
</script>

<template>
    <section class="page-heading" aria-labelledby="page-title">
        <img class="hero-backdrop" :src="event.images.hero" alt="" decoding="async">
        <div class="page-container">
            <p class="eyebrow">{{ event.name }} {{ event.year }}</p>
            <h1 id="page-title">{{ page.heading }}</h1>
            <p>{{ page.description }}</p>
        </div>
    </section>
</template>
