<script setup>
import { computed } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
import ActionButton from '../ui/ActionButton.vue';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    challenges: { type: Array, required: true },
    raisedCents: { type: Number, required: true },
    href: { type: String, required: true },
});

const displayedChallenges = computed(() => {
    const upcomingChallenges = props.challenges.filter((challenge) => challenge.amountCents > props.raisedCents);

    if (upcomingChallenges.length === 0) {
        return props.challenges.slice(-4);
    }

    const displayed = upcomingChallenges.slice(0, 3);
    const nextFeaturedChallenge = upcomingChallenges.find((challenge) => challenge.isFeatured);

    if (nextFeaturedChallenge && !displayed.includes(nextFeaturedChallenge)) {
        displayed.push(nextFeaturedChallenge);
        displayed.sort((first, second) => first.amountCents - second.amountCents);
    }

    return displayed;
});
</script>

<template>
    <section id="challenges" class="content-card challenges-card" aria-labelledby="challenges-title">
        <p class="section-label">Challenges</p>
        <h2 id="challenges-title">Hoe meer we ophalen,<br>hoe meer we doen!</h2>
        <ul class="challenge-list">
            <li v-for="challenge in displayedChallenges" :key="challenge.amountCents">
                <AppIcon :name="raisedCents >= challenge.amountCents ? 'check' : 'lock'" :class="{ unlocked: raisedCents >= challenge.amountCents }" />
                <strong>{{ formatMoney(challenge.amountCents) }}</strong>
                <span>{{ challenge.name }}<small v-if="challenge.isFeatured">{{ challenge.description }}</small></span>
            </li>
        </ul>
        <ActionButton :href="href" variant="outline" icon="trophy" arrow class="card-bottom-action">Bekijk alle challenges</ActionButton>
    </section>
</template>
