<script setup>
import AppIcon from '../ui/AppIcon.vue';

defineProps({ items: { type: Array, required: true } });
</script>

<template>
    <section class="event-highlights" aria-label="Samen voor het goede doel">
        <ul class="page-container highlight-grid">
            <li v-for="item in items" :key="item.title">
                <AppIcon :name="item.icon" />
                <div><h2>{{ item.title }}</h2><p>{{ item.text }}</p></div>
            </li>
        </ul>
    </section>
</template>
