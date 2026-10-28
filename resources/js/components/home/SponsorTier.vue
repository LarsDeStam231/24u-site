<script setup>
import { computed } from 'vue';
import AppIcon from '../ui/AppIcon.vue';

const props = defineProps({ tier: { type: Object, required: true } });
const emptySlots = computed(() => Math.max(0, 3 - props.tier.sponsors.length));
</script>

<template>
    <section class="sponsor-tier" :class="`sponsor-tier--${tier.id}`" :aria-label="`${tier.name} sponsoren`">
        <header><AppIcon name="trophy" /><div><h3>{{ tier.name }}</h3><p>{{ tier.price }}</p></div></header>
        <div class="sponsor-slots">
            <component :is="sponsor.url ? 'a' : 'span'" v-for="sponsor in tier.sponsors" :key="sponsor.name" :href="sponsor.url || undefined" :target="sponsor.url ? '_blank' : undefined" :rel="sponsor.url ? 'noopener noreferrer' : undefined" class="sponsor-slot">
                <img v-if="sponsor.logo" :src="sponsor.logo" :alt="sponsor.name" loading="lazy"><span v-else>{{ sponsor.name }}</span>
            </component>
            <span v-for="slot in emptySlots" :key="`empty-${slot}`" class="sponsor-slot sponsor-slot--empty">Uw logo hier</span>
        </div>
    </section>
</template>
