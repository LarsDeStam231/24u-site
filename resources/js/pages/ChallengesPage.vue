<script setup>
import { computed, ref } from 'vue';
import PageHeading from '../components/layout/PageHeading.vue';
import DonationCounter from '../components/home/DonationCounter.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import AppIcon from '../components/ui/AppIcon.vue';
import { formatMoney } from '../utils/format';

const props = defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});

const raisedCents = ref(props.event.fundraising.raisedCents);
const challenges = computed(() => [...props.event.challenges].sort((first, second) => first.amountCents - second.amountCents));
const activeFilter = ref('all');
const nextChallenge = computed(() => challenges.value.find((challenge) => challenge.amountCents > raisedCents.value) ?? null);

const isUnlocked = (challenge) => raisedCents.value >= challenge.amountCents;
const challengeProgress = (challenge) => Math.max(0, Math.min(100, raisedCents.value / Math.max(1, challenge.amountCents) * 100));
const nextChallengeProgress = computed(() => nextChallenge.value ? challengeProgress(nextChallenge.value) : 100);
const unlockedChallenges = computed(() => challenges.value.filter((challenge) => isUnlocked(challenge)).length);
const challengeFilters = computed(() => [
    { id: 'all', label: 'Alle', count: challenges.value.length },
    { id: 'upcoming', label: 'Nog te halen', count: challenges.value.length - unlockedChallenges.value },
    { id: 'unlocked', label: 'Vrijgespeeld', count: unlockedChallenges.value },
]);
const filteredChallenges = computed(() => challenges.value.filter((challenge) => {
    if (activeFilter.value === 'upcoming') {
        return !isUnlocked(challenge);
    }

    if (activeFilter.value === 'unlocked') {
        return isUnlocked(challenge);
    }

    return true;
}));

function updateRaisedCents(amount) {
    raisedCents.value = amount;
}
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container detail-content challenge-page">
            <section class="challenge-spotlight" aria-label="Volgende challenge">
                <div class="challenge-spotlight-copy">
                    <p class="challenge-kicker"><AppIcon name="trophy" /> Volgende mijlpaal</p>
                    <template v-if="nextChallenge">
                        <h2>{{ nextChallenge.name }}</h2>
                        <p class="challenge-spotlight-description">{{ nextChallenge.description }}</p>
                        <div class="challenge-spotlight-target">
                            <span>Doel: {{ formatMoney(nextChallenge.amountCents) }}</span>
                            <strong>Nog {{ formatMoney(nextChallenge.amountCents - raisedCents) }} nodig</strong>
                        </div>
                        <div class="challenge-spotlight-progress" role="progressbar" :aria-label="`Voortgang naar ${nextChallenge.name}`" :aria-valuenow="Number(nextChallengeProgress.toFixed(2))" aria-valuemin="0" aria-valuemax="100" :aria-valuetext="`${formatMoney(Math.min(raisedCents, nextChallenge.amountCents))} van ${formatMoney(nextChallenge.amountCents)}`">
                            <span :style="{ width: `${nextChallengeProgress}%` }"></span>
                        </div>
                    </template>
                    <template v-else>
                        <h2>Alle mijlpalen zijn behaald!</h2>
                        <p class="challenge-spotlight-description">Dankzij alle bijdragen zijn alle challenges vrijgespeeld. Wat een prestatie!</p>
                    </template>
                </div>
                <DonationCounter :fundraising="event.fundraising" :challenges="event.challenges" :endpoint="links.donationTotal" :show-next-challenge="false" @raised-cents-updated="updateRaisedCents" />
            </section>

            <section class="challenge-list-section" aria-labelledby="challenge-list-title">
                <div class="challenge-list-heading">
                    <div>
                        <p class="challenge-kicker">Samen komen we verder</p>
                        <h2 id="challenge-list-title">De challenge-mijlpalen</h2>
                    </div>
                    <p class="challenge-list-count"><strong>{{ unlockedChallenges }}</strong> van {{ challenges.length }} behaald</p>
                </div>

                <div class="challenge-filters" role="group" aria-label="Filter challenges">
                    <button v-for="filter in challengeFilters" :key="filter.id" type="button" :class="{ 'challenge-filter--active': activeFilter === filter.id }" :aria-pressed="activeFilter === filter.id" @click="activeFilter = filter.id">
                        {{ filter.label }} <span>{{ filter.count }}</span>
                    </button>
                </div>

                <div v-if="filteredChallenges.length" class="challenge-grid">
                    <article v-for="challenge in filteredChallenges" :key="challenge.amountCents" class="challenge-card" :class="{ 'challenge-card--unlocked': isUnlocked(challenge), 'challenge-card--next': nextChallenge === challenge, 'challenge-card--featured': challenge.isFeatured, 'challenge-card--headline': challenge.isHeadline }">
                        <div class="challenge-card-topline">
                            <span class="challenge-card-target">{{ formatMoney(challenge.amountCents) }}</span>
                            <span v-if="challenge.isHeadline" class="challenge-headline-badge">Headline</span>
                            <span class="challenge-status"><AppIcon :name="isUnlocked(challenge) ? 'check' : challenge === nextChallenge ? 'gift' : 'lock'" />{{ isUnlocked(challenge) ? 'Behaald' : challenge === nextChallenge ? 'Volgende' : 'Vergrendeld' }}</span>
                        </div>
                        <h3>{{ challenge.name }}</h3>
                        <p class="challenge-card-description">{{ challenge.description }}</p>
                        <div class="challenge-card-progress" role="progressbar" :aria-label="`Voortgang naar ${challenge.name}`" :aria-valuenow="Number(challengeProgress(challenge).toFixed(2))" aria-valuemin="0" aria-valuemax="100" :aria-valuetext="`${formatMoney(Math.min(raisedCents, challenge.amountCents))} van ${formatMoney(challenge.amountCents)}`">
                            <span :style="{ width: `${challengeProgress(challenge)}%` }"></span>
                        </div>
                        <div class="challenge-card-footer">
                            <span>{{ formatMoney(Math.min(raisedCents, challenge.amountCents)) }} / {{ formatMoney(challenge.amountCents) }}</span>
                            <strong v-if="!isUnlocked(challenge)">Nog {{ formatMoney(challenge.amountCents - raisedCents) }}</strong>
                            <strong v-else>Doel behaald</strong>
                        </div>
                    </article>
                </div>
                <p v-else class="challenge-empty-state">
                    <span v-if="activeFilter === 'unlocked'">Er zijn nog geen challenges vrijgespeeld.<template v-if="challenges.length"> De eerste mijlpaal wacht op {{ formatMoney(challenges[0].amountCents) }}.</template></span>
                    <span v-else>Alle challenges zijn vrijgespeeld. Wat een prestatie!</span>
                </p>
            </section>

            <section class="challenge-callout">
                <div class="content-card prose-card">
                    <div>
                        <p class="challenge-kicker">Iedere bijdrage telt</p>
                        <h2>Help ons de volgende mijlpaal halen</h2>
                        <p>Elke bijdrage brengt de challenges dichterbij en steunt {{ event.foundation.name }}.</p>
                    </div>
                    <ActionButton :href="links.donations" icon="heart">Doneer nu</ActionButton>
                    <ActionButton :href="links.livestream" variant="light-outline" icon="play">Bekijk de livestream</ActionButton>
                </div>
            </section>
        </div>
    </div>
</template>
