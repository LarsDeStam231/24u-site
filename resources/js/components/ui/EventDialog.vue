<script setup>
import { useId, useTemplateRef } from 'vue';
import AppIcon from './AppIcon.vue';

defineProps({ title: { type: String, required: true } });

const dialog = useTemplateRef('dialog');
const titleId = useId();

const open = () => dialog.value.showModal();
const close = () => dialog.value.close();

function closeOnBackdrop(event) {
    if (event.target !== dialog.value) {
        return;
    }

    const bounds = dialog.value.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
        close();
    }
}
</script>

<template>
    <slot name="trigger" :open="open" />
    <dialog ref="dialog" class="event-dialog" :aria-labelledby="titleId" @click="closeOnBackdrop">
        <header class="dialog-header">
            <h2 :id="titleId">{{ title }}</h2>
            <button class="icon-button" type="button" aria-label="Venster sluiten" @click="close"><AppIcon name="close" /></button>
        </header>
        <slot :close="close" />
    </dialog>
</template>
