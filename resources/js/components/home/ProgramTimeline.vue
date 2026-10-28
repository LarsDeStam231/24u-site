<script setup>
import { formatDate } from '../../utils/format';

defineProps({ items: { type: Array, required: true } });
</script>

<template>
    <ol class="program-timeline">
        <li v-for="item in items" :key="item.id">
            <div class="timeline-when"><time :datetime="item.date">{{ formatDate(`${item.date}T12:00:00+01:00`, { weekday: 'short', month: 'short' }) }}</time><span>{{ item.time }}</span></div>
            <div class="timeline-description"><h3>{{ item.title }}</h3><p>{{ item.description }}</p></div>
        </li>
    </ol>
</template>
