<script setup>
import { formatDate } from '../../utils/format';

defineProps({
    items: { type: Array, required: true },
    showDate: { type: Boolean, default: true },
});
</script>

<template>
    <ol class="program-timeline" :class="{ 'program-timeline--full': !showDate }">
        <li v-for="item in items" :key="item.id">
            <div class="timeline-when">
                <time v-if="showDate" :datetime="item.date">{{ formatDate(`${item.date}T12:00:00+01:00`, { weekday: 'short', month: 'short' }) }}</time>
                <span class="timeline-time-range">{{ item.time }}</span>
            </div>
            <div class="timeline-description">
                <h3>{{ item.title }}</h3>
                <p>{{ item.description }}</p>
                <dl v-if="item.responsible || item.presenter || item.production || item.materials || item.donationChallenge" class="timeline-meta">
                    <div v-if="item.responsible"><dt>Student(en) / verantwoordelijke</dt><dd>{{ item.responsible }}</dd></div>
                    <div v-if="item.presenter"><dt>Presentator(en)</dt><dd>{{ item.presenter }}</dd></div>
                    <div v-if="item.production"><dt>OBS / regie / techniek</dt><dd>{{ item.production }}</dd></div>
                    <div v-if="item.materials"><dt>Materialen / gast</dt><dd>{{ item.materials }}</dd></div>
                    <div v-if="item.donationChallenge"><dt>Donatie / challenge</dt><dd>{{ item.donationChallenge }}</dd></div>
                </dl>
            </div>
        </li>
    </ol>
</template>
