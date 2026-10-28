<script setup>
import { computed } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    fundraising: { type: Object, required: true },
    challenges: { type: Array, required: true },
});

const percentage = computed(() => Math.max(0, Math.min(100, props.fundraising.raisedCents / Math.max(1, props.fundraising.goalCents) * 100)));
const percentageLabel = computed(() => new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 2 }).format(percentage.value));
const nextChallenge = computed(() => [...props.challenges]
    .sort((first, second) => first.amountCents - second.amountCents)
    .find((challenge) => challenge.amountCents > props.fundraising.raisedCents));
</script>

<template>
    <div class="donation-counter">
        <div class="counter-heading"><h2>Live donatieteller</h2><span v-if="fundraising.isPreview" class="preview-badge">Voorbeeld</span></div>
        <div class="counter-amounts">
            <strong class="counter-total">{{ formatMoney(fundraising.raisedCents) }}</strong>
            <span>van {{ formatMoney(fundraising.goalCents) }}</span>
        </div>
        <div class="progress-track" role="progressbar" aria-label="Voortgang donatiedoel" :aria-valuenow="Number(percentage.toFixed(2))" aria-valuemin="0" aria-valuemax="100" :aria-valuetext="`${formatMoney(fundraising.raisedCents)} van ${formatMoney(fundraising.goalCents)}`">
            <span :style="{ width: `${percentage}%` }"></span>
        </div>
        <p class="counter-percentage"><span>{{ percentageLabel }}%</span> opgehaald</p>
        <p class="next-challenge"><AppIcon name="gift" /><span v-if="nextChallenge">Nog <strong>{{ formatMoney(nextChallenge.amountCents - fundraising.raisedCents) }}</strong> tot de volgende challenge!</span><span v-else>Alle mijlpalen behaald. Wat een prestatie!</span></p>
    </div>
</template>
