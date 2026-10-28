<script setup>
import SiteBrand from './SiteBrand.vue';
import SocialLinks from './SocialLinks.vue';
import FoundationLogo from '../home/FoundationLogo.vue';
import AppIcon from '../ui/AppIcon.vue';

defineProps({
    event: { type: Object, required: true },
    navigation: { type: Array, required: true },
    socials: { type: Array, required: true },
    links: { type: Object, required: true },
    currentRoute: { type: String, required: true },
});
</script>

<template>
    <footer class="site-footer">
        <div class="page-container footer-grid">
            <div class="footer-brand">
                <SiteBrand :image="event.images.brand" :href="links.home" />
                <p><strong>24 uur. 1 goed doel. Samen sterker.</strong><br>Voor {{ event.foundation.name }}.</p>
            </div>
            <div>
                <h2>Snelle links</h2>
                <nav class="footer-navigation" aria-label="Footernavigatie">
                    <a v-for="item in navigation" :key="item.route" :href="item.href" :aria-current="currentRoute === item.route ? 'page' : undefined">{{ item.label }}</a>
                </nav>
            </div>
            <div class="footer-contact">
                <h2>Contact</h2>
                <p><AppIcon name="map" /><span>{{ event.location.street }}, {{ event.location.postcode }} {{ event.location.city }}</span></p>
                <a :href="`mailto:${event.email}`"><AppIcon name="mail" />{{ event.email }}</a>
                <SocialLinks :links="socials" />
            </div>
            <div id="over-ons" class="footer-foundation">
                <h2>In samenwerking met</h2>
                <FoundationLogo :foundation="event.foundation" :image="event.images.foundation" />
                <p>{{ event.foundation.description }}</p>
            </div>
        </div>
    </footer>
</template>
