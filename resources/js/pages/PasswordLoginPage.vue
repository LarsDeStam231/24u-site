<script setup>
import PageHeading from '../components/layout/PageHeading.vue';
import LoginForm from '../components/ui/LoginForm.vue';

defineProps({ page: { type: Object, required: true }, event: { type: Object, required: true }, links: { type: Object, required: true } });
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container py-10">
            <LoginForm :links="links" :endpoints="{ options: page.optionsUrl, login: page.submitUrl, mail: page.mailUrl, pairCreate: page.pairCreateUrl, pairConsume: page.pairConsumeUrl, pairCancel: page.pairCancelUrl }" />
        </div>
    </div>
</template>
