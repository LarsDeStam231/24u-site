<script setup>
import AppIcon from '../ui/AppIcon.vue';
import ActionButton from '../ui/ActionButton.vue';
import { formatMoney } from '../../utils/format';

defineProps({
    challenges: { type: Array, required: true },
    raisedCents: { type: Number, required: true },
    isPreview: { type: Boolean, default: false },
    href: { type: String, required: true },
});
</script>

<template>
    <section id="challenges" class="content-card challenges-card" aria-labelledby="challenges-title">
        <p class="section-label">Challenges</p>
        <h2 id="challenges-title">Hoe meer we ophalen,<br>hoe meer we doen!</h2>
        <ul class="challenge-list">
            <li v-for="challenge in challenges" :key="challenge.amountCents">
                <AppIcon :name="!isPreview && raisedCents >= challenge.amountCents ? 'check' : 'lock'" :class="{ unlocked: !isPreview && raisedCents >= challenge.amountCents }" />
                <strong>{{ formatMoney(challenge.amountCents) }}</strong>
                <span>{{ challenge.name }}<small v-if="challenge === challenges.at(-1)">{{ challenge.description }}</small></span>
            </li>
        </ul>
        <ActionButton :href="href" variant="outline" icon="trophy" arrow class="card-bottom-action">Bekijk alle challenges</ActionButton>
    </section>
</template>
