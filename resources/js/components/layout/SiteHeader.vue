<script setup>
import { onMounted, onUnmounted, ref, useTemplateRef } from 'vue';
import SiteBrand from './SiteBrand.vue';
import SocialLinks from './SocialLinks.vue';
import AppIcon from '../ui/AppIcon.vue';
import ActionButton from '../ui/ActionButton.vue';

defineProps({
    brandImage: { type: String, required: true },
    navigation: { type: Array, required: true },
    accountNavigation: { type: Array, required: true },
    socials: { type: Array, required: true },
    links: { type: Object, required: true },
    currentRoute: { type: String, required: true },
});

const menuOpen = ref(false);
const menuToggle = useTemplateRef('menuToggle');
let desktopQuery;

function closeForDesktop(event) {
    if (event.matches) {
        menuOpen.value = false;
    }
}

function closeWithEscape(event) {
    if (event.key === 'Escape' && menuOpen.value) {
        menuOpen.value = false;
        menuToggle.value.focus();
    }
}

onMounted(() => {
    desktopQuery = window.matchMedia('(min-width: 75rem)');
    desktopQuery.addEventListener('change', closeForDesktop);
    window.addEventListener('keydown', closeWithEscape);
});

onUnmounted(() => {
    desktopQuery?.removeEventListener('change', closeForDesktop);
    window.removeEventListener('keydown', closeWithEscape);
});
</script>

<template>
    <header class="site-header">
        <div class="page-container header-content">
            <SiteBrand :image="brandImage" :href="links.home" @click="menuOpen = false" />
            <nav class="desktop-navigation" aria-label="Hoofdnavigatie">
                <a v-for="item in navigation" :key="item.route" :href="item.href" :aria-current="currentRoute === item.route ? 'page' : undefined">{{ item.label }}</a>
            </nav>
            <div class="header-actions">
                <nav aria-label="Account" class="hidden items-center gap-2 min-[75rem]:flex">
                    <a v-for="item in accountNavigation" :key="item.route" :href="item.href" :aria-current="currentRoute === item.route ? 'page' : undefined" class="rounded border border-white/40 px-3 py-2 text-sm font-bold hover:bg-white/10">{{ item.label }}</a>
                </nav>
                <SocialLinks :links="socials" />
                <ActionButton :href="links.donations" icon="heart" class="header-donate" @click="menuOpen = false">Doneer nu</ActionButton>
                <button ref="menuToggle" class="icon-button menu-toggle" type="button" :aria-expanded="menuOpen" aria-controls="mobile-navigation" :aria-label="menuOpen ? 'Menu sluiten' : 'Menu openen'" @click="menuOpen = !menuOpen">
                    <AppIcon :name="menuOpen ? 'close' : 'menu'" />
                </button>
            </div>
        </div>
        <nav v-show="menuOpen" id="mobile-navigation" class="mobile-navigation" aria-label="Mobiele navigatie">
            <a v-for="item in accountNavigation" :key="item.route" :href="item.href" :aria-current="currentRoute === item.route ? 'page' : undefined" class="font-bold" @click="menuOpen = false">{{ item.label }}</a>
            <a v-for="item in navigation" :key="item.route" :href="item.href" :aria-current="currentRoute === item.route ? 'page' : undefined" @click="menuOpen = false">{{ item.label }}</a>
        </nav>
    </header>
</template>
