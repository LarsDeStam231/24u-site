<script setup>
import AppIcon from '../ui/AppIcon.vue';
import { formatDate } from '../../utils/format';

defineProps({
    startsAt: { type: String, required: true },
    endsAt: { type: String, required: true },
    location: { type: Object, required: true },
});

const time = (date) => formatDate(date, { day: undefined, month: undefined, hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <div class="event-details">
        <div class="event-detail">
            <AppIcon name="calendar" />
            <span><strong><time :datetime="startsAt">{{ formatDate(startsAt, { year: 'numeric' }) }} {{ time(startsAt) }}</time></strong><br>t/m <time :datetime="endsAt">{{ formatDate(endsAt, { year: 'numeric' }) }} {{ time(endsAt) }}</time></span>
        </div>
        <div class="event-detail">
            <AppIcon name="map" />
            <span><strong>{{ location.name }}</strong><br>{{ location.street }}, {{ location.city }}</span>
        </div>
    </div>
</template>
