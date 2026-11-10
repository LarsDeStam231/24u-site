<script setup>
const props = defineProps({
    modelValue: { type: String, required: true },
    items: { type: Array, required: true },
    label: { type: String, required: true },
    id: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);

function navigate(event, index) {
    let next;
    if (event.key === 'ArrowRight') next = (index + 1) % props.items.length;
    else if (event.key === 'ArrowLeft') next = (index - 1 + props.items.length) % props.items.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = props.items.length - 1;
    else return;

    event.preventDefault();
    emit('update:modelValue', props.items[next].id);
    event.currentTarget.parentElement.querySelectorAll('[role="tab"]')[next].focus();
}
</script>

<template>
    <nav class="account-tabs" role="tablist" :aria-label="label">
        <button v-for="(item, index) in items" :id="`${id}-tab-${item.id}`" :key="item.id" type="button" role="tab" class="account-tab" :aria-selected="modelValue === item.id" :aria-controls="`${id}-panel-${item.id}`" :tabindex="modelValue === item.id ? 0 : -1" @click="emit('update:modelValue', item.id)" @keydown="navigate($event, index)">
            {{ item.label }}
        </button>
    </nav>
</template>
