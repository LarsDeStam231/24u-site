<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    fundraising: { type: Object, required: true },
    challenges: { type: Array, required: true },
    endpoint: { type: String, required: true },
    title: { type: String, default: 'Live donatieteller' },
    showNextChallenge: { type: Boolean, default: true },
    showGoalStatus: { type: Boolean, default: false },
});
const emit = defineEmits(['raisedCentsUpdated']);

const raisedCents = ref(props.fundraising.raisedCents);
const percentage = computed(() => Math.max(0, raisedCents.value / Math.max(1, props.fundraising.goalCents) * 100));
const progressPercentage = computed(() => Math.min(100, percentage.value));
const goalReached = computed(() => percentage.value >= 100);
const percentageLabel = computed(() => new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 2 }).format(percentage.value));
const nextChallenge = computed(() => [...props.challenges]
    .sort((first, second) => first.amountCents - second.amountCents)
    .find((challenge) => challenge.amountCents > raisedCents.value));

let refreshInterval;
let refreshPending = false;
let disposed = false;

function updateRaisedAmount(result) {
    if (!disposed && Number.isSafeInteger(result.raisedCents) && result.raisedCents >= 0) {
        raisedCents.value = result.raisedCents;
        emit('raisedCentsUpdated', result.raisedCents);
    }
}

async function refreshRaisedAmount() {
    if (disposed || refreshPending) {
        return;
    }

    refreshPending = true;

    try {
        const response = await fetch(props.endpoint, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            return;
        }

        updateRaisedAmount(await response.json());
    } catch {
        // Keep the last known total when refreshing temporarily fails.
    } finally {
        refreshPending = false;
    }
}

onMounted(() => {
    refreshRaisedAmount();
    refreshInterval = window.setInterval(refreshRaisedAmount, 10_000);
});

onUnmounted(() => {
    disposed = true;
    window.clearInterval(refreshInterval);
});
</script>

<template>
    <div class="donation-counter" :class="{ 'donation-counter--goal-reached': showGoalStatus && goalReached }">
        <div class="counter-heading"><h2>{{ title }}</h2></div>
        <div class="counter-amounts">
            <strong class="counter-total">{{ formatMoney(raisedCents) }}</strong>
            <span>van {{ formatMoney(fundraising.goalCents) }}</span>
        </div>
        <div class="progress-track" role="progressbar" aria-label="Voortgang donatiedoel" :aria-valuenow="Number(progressPercentage.toFixed(2))" aria-valuemin="0" aria-valuemax="100" :aria-valuetext="`${formatMoney(raisedCents)} van ${formatMoney(fundraising.goalCents)}`">
            <span :style="{ width: `${progressPercentage}%` }"></span>
        </div>
        <p class="counter-percentage"><span>{{ percentageLabel }}%</span> opgehaald</p>
        <p v-if="showGoalStatus" class="counter-goal-status" role="status">
            <AppIcon :name="goalReached ? 'check' : 'heart'" />
            <span v-if="goalReached">Doel behaald! Je kunt gewoon blijven inzamelen.</span>
            <span v-else>Nog <strong>{{ formatMoney(fundraising.goalCents - raisedCents) }}</strong> tot jouw doel.</span>
        </p>
        <p v-if="showNextChallenge" class="next-challenge"><AppIcon name="gift" /><span v-if="nextChallenge">Nog <strong>{{ formatMoney(nextChallenge.amountCents - raisedCents) }}</strong> tot de volgende challenge!</span><span v-else>Alle mijlpalen behaald. Wat een prestatie!</span></p>
    </div>
</template>
