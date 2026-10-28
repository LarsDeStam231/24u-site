<script setup>
import ActionButton from '../ui/ActionButton.vue';
import { formatDate } from '../../utils/format';

defineProps({
    stream: { type: Object, required: true },
    image: { type: String, required: true },
    startsAt: { type: String, required: true },
});
</script>

<template>
    <section id="livestream" class="livestream-card" aria-labelledby="livestream-title">
        <a :href="stream.url" target="_blank" rel="noopener noreferrer" class="stream-image-link" aria-label="Bekijk de livestream op YouTube (opent in nieuw tabblad)">
            <img :src="image" alt="DCTerra gamingstudio, bekijk de livestream" width="1200" height="605" decoding="async">
            <span v-if="stream.live" class="stream-live-badge">● Nu live</span>
        </a>
        <div class="stream-caption">
            <h2 id="livestream-title">{{ stream.live ? 'We zijn live! Kijk je mee?' : `Livestream start ${formatDate(startsAt)} om ${formatDate(startsAt, { day: undefined, month: undefined, hour: '2-digit', minute: '2-digit' })}` }}</h2>
            <p>Bekijk hier de volledige 24 uur livestream via YouTube.</p>
            <ActionButton :href="stream.url" icon="youtube" arrow target="_blank" rel="noopener noreferrer">Bekijk op YouTube</ActionButton>
        </div>
    </section>
</template>
