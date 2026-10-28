<script setup>
import SiteHeader from '../components/layout/SiteHeader.vue';
import SiteFooter from '../components/layout/SiteFooter.vue';

defineProps({
    event: { type: Object, required: true },
    navigation: { type: Array, required: true },
    accountNavigation: { type: Array, required: true },
    socials: { type: Array, required: true },
    links: { type: Object, required: true },
    currentRoute: { type: String, required: true },
});
</script>

<template>
    <div id="home" class="site-shell">
        <a class="skip-link" href="#main-content">Direct naar de inhoud</a>
        <SiteHeader :brand-image="event.images.brand" :navigation="navigation" :account-navigation="accountNavigation" :socials="socials" :links="links" :current-route="currentRoute" />
        <main id="main-content" tabindex="-1">
            <slot />
        </main>
        <SiteFooter :event="event" :navigation="navigation" :socials="socials" :links="links" :current-route="currentRoute" />
    </div>
</template>
