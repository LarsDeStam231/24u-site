<script setup>
import { ref } from 'vue';
import MainLayout from './layouts/MainLayout.vue';
import HomePage from './pages/HomePage.vue';
import LivestreamPage from './pages/LivestreamPage.vue';
import DonationPage from './pages/DonationPage.vue';
import ChallengesPage from './pages/ChallengesPage.vue';
import ProgramPage from './pages/ProgramPage.vue';
import SponsorsPage from './pages/SponsorsPage.vue';
import AboutPage from './pages/AboutPage.vue';
import QrPage from './pages/QrPage.vue';
import RegistrationPage from './pages/RegistrationPage.vue';
import PasswordLoginPage from './pages/PasswordLoginPage.vue';

const props = defineProps({
    page: { type: Object, required: true },
    event: { type: Object, required: true },
    navigation: { type: Array, required: true },
    accountNavigation: { type: Array, required: true },
    socials: { type: Array, required: true },
    links: { type: Object, required: true },
    currentRoute: { type: String, required: true },
    qr: { type: Object, default: () => ({}) },
});

const accountLinks = ref(props.accountNavigation);

function showAccountNavigation() {
    accountLinks.value = [{ label: 'Mijn account', href: props.links.account, route: 'account.index' }];
}

const pages = {
    home: HomePage,
    livestream: LivestreamPage,
    donations: DonationPage,
    challenges: ChallengesPage,
    program: ProgramPage,
    sponsors: SponsorsPage,
    about: AboutPage,
    qr: QrPage,
    registration: RegistrationPage,
    'password-login': PasswordLoginPage,
};
</script>

<template>
    <MainLayout :event="event" :navigation="navigation" :account-navigation="accountLinks" :socials="socials" :links="links" :current-route="currentRoute">
        <component :is="pages[page.name]" :event="event" :page="page" :links="links" v-bind="page.name === 'qr' ? { qr } : {}" v-on="page.name === 'qr' ? { authenticated: showAccountNavigation } : {}" />
    </MainLayout>
</template>
