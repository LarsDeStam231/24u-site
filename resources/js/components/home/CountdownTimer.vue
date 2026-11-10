<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatDate } from '../../utils/format';

const props = defineProps({
    targetDate: { type: String, required: true },
    endDate: { type: String, required: true },
    isLive: { type: Boolean, default: false },
});

const currentTime = ref(Date.now());
const targetTime = computed(() => new Date(props.targetDate).getTime());
const endTime = computed(() => new Date(props.endDate).getTime());
const secondsRemaining = computed(() => Math.max(0, Math.floor((targetTime.value - currentTime.value) / 1000)));
const isFinished = computed(() => currentTime.value >= endTime.value);
const days = computed(() => Math.floor(secondsRemaining.value / 86400));
const hours = computed(() => Math.floor((secondsRemaining.value % 86400) / 3600));
const minutes = computed(() => Math.floor((secondsRemaining.value % 3600) / 60));
const seconds = computed(() => secondsRemaining.value % 60);
const heading = computed(() => isFinished.value ? 'De livestream is afgelopen' : props.isLive ? 'We zijn live!' : secondsRemaining.value > 0 ? 'We gaan live over' : 'De starttijd is aangebroken!');
const targetLabel = computed(() => formatDate(props.targetDate, {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
}));

let timer;

onMounted(() => {
    timer = window.setInterval(() => {
        currentTime.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    window.clearInterval(timer);
});
</script>

<template>
    <section class="livestream-countdown" aria-labelledby="livestream-countdown-title">
        <div class="livestream-countdown-heading">
            <p class="livestream-countdown-eyebrow">DCTerra 24 Uur Live</p>
            <h2 id="livestream-countdown-title">{{ heading }}</h2>
            <p v-if="!isLive && !isFinished && secondsRemaining > 0" class="livestream-countdown-date"><time :datetime="targetDate">{{ targetLabel }}</time></p>
        </div>

        <div v-if="isFinished" class="livestream-countdown-live" role="status">Deze livestream is afgelopen.</div>
        <div v-else-if="isLive" class="livestream-countdown-live" role="status">De livestream is nu bezig. Kijk mee!</div>
        <div v-else-if="secondsRemaining > 0" class="livestream-countdown-units" role="timer" aria-live="off" :aria-label="`${days} dagen, ${hours} uur, ${minutes} minuten en ${seconds} seconden tot de livestream start`">
            <div><strong>{{ days }}</strong><span>Dagen</span></div>
            <div><strong>{{ String(hours).padStart(2, '0') }}</strong><span>Uur</span></div>
            <div><strong>{{ String(minutes).padStart(2, '0') }}</strong><span>Minuten</span></div>
            <div><strong>{{ String(seconds).padStart(2, '0') }}</strong><span>Seconden</span></div>
        </div>
        <p v-else class="livestream-countdown-live" role="status">De starttijd is aangebroken!</p>
    </section>
</template>
