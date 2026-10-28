<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    href: { type: String, default: null },
    variant: { type: String, default: 'primary' },
    icon: { type: String, default: null },
    arrow: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});
</script>

<template>
    <component :is="href ? 'a' : 'button'" :href="href || undefined" :type="href ? undefined : type" class="action-button" :class="`action-button--${variant}`">
        <AppIcon v-if="icon" :name="icon" />
        <span><slot /></span>
        <AppIcon v-if="arrow" name="arrow" class="button-arrow" />
    </component>
</template>
