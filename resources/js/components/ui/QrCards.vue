<script setup>
import ActionButton from './ActionButton.vue';

defineProps({ codes: { type: Object, required: true } });

const labels = { login: 'Inloggen — privé', attendance: 'Aanwezigheid', donation: 'Mijn donatiepagina' };
const descriptions = {
    login: 'Eenmalig zichtbaar. Bewaar deze code veilig en deel hem met niemand: hij geeft toegang tot jouw account.',
    attendance: 'Laat deze code bij aankomst en vertrek scannen door de organisatie.',
    donation: 'Deel deze code om mensen naar jouw persoonlijke donatiepagina te sturen.',
};
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <section v-for="(code, type) in codes" :key="type" class="content-card grid content-start gap-4 p-6">
            <h2 class="text-xl">{{ labels[type] }}</h2>
            <p>{{ descriptions[type] }}</p>
            <img :src="code.image" :alt="`QR-code: ${labels[type]}`" width="320" height="320" class="mx-auto w-full max-w-80 bg-white p-2" />
            <a :href="code.image" :download="`24u-${type}.svg`" class="action-button action-button--primary">Download QR-code</a>
            <label class="grid gap-2 text-sm font-bold">
                {{ type === 'login' ? 'Privé-inloglink (veilig bewaren)' : 'Bijbehorende link' }}
                <input :value="code.url" readonly class="w-full rounded border border-slate-300 bg-white p-3 font-normal" :type="type === 'login' ? 'password' : 'text'" @focus="$event.target.select()" />
            </label>
            <ActionButton v-if="type === 'donation'" :href="code.url" variant="outline">Bekijk mijn pagina</ActionButton>
        </section>
    </div>
</template>
