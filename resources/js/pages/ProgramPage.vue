<script setup>
import { computed } from 'vue';
import PageHeading from '../components/layout/PageHeading.vue';
import ProgramTimeline from '../components/home/ProgramTimeline.vue';
import EventDetails from '../components/home/EventDetails.vue';
import ActionButton from '../components/ui/ActionButton.vue';
import { formatDate } from '../utils/format';

const props = defineProps({
    event: { type: Object, required: true },
    page: { type: Object, required: true },
    links: { type: Object, required: true },
});

const programDays = computed(() => {
    const groupedDays = new Map();

    for (const item of props.event.schedule) {
        if (!groupedDays.has(item.date)) {
            groupedDays.set(item.date, []);
        }

        groupedDays.get(item.date).push(item);
    }

    return [...groupedDays.entries()].map(([date, items], index) => ({
        id: `programma-${date}`,
        date,
        label: formatDate(`${date}T12:00:00+01:00`, { weekday: 'long', day: 'numeric', month: 'long' }),
        number: String(index + 1).padStart(2, '0'),
        items,
    }));
});
</script>

<template>
    <div>
        <PageHeading :page="page" :event="event" />
        <div class="page-container detail-content program-page">
            <section class="program-overview" aria-labelledby="schedule-title">
                <div class="program-overview-copy">
                    <p class="program-eyebrow">Van aftrap tot finale</p>
                    <h2 id="schedule-title">24 uur. Eén doorlopend programma.</h2>
                    <p>Bekijk de planning per dag en vind snel het blok dat je wilt volgen.</p>
                </div>
                <div class="program-overview-stats" aria-label="Programma in cijfers">
                    <div><strong>24</strong><span>uur live</span></div>
                    <div><strong>{{ programDays.length }}</strong><span>dagen</span></div>
                    <div><strong>{{ event.schedule.length }}</strong><span>onderdelen</span></div>
                </div>
            </section>

            <nav class="program-day-nav" aria-label="Spring naar een programmadag">
                <a v-for="day in programDays" :key="day.id" :href="`#${day.id}`">
                    <span>Dag {{ day.number }}</span>
                    <strong>{{ day.label }}</strong>
                </a>
            </nav>

            <div class="program-days">
                <section v-for="day in programDays" :id="day.id" :key="day.id" class="program-day-card" :aria-labelledby="`${day.id}-title`">
                    <header class="program-day-header">
                        <div class="program-day-number">Dag {{ day.number }}</div>
                        <div>
                            <p>24 Uur Live</p>
                            <h2 :id="`${day.id}-title`">{{ day.label }}</h2>
                        </div>
                        <span class="program-day-count">{{ day.items.length }} onderdelen</span>
                    </header>
                    <div class="program-day-body">
                        <ProgramTimeline :items="day.items" :show-date="false" />
                    </div>
                </section>
            </div>

            <section class="program-practical">
                <div>
                    <p class="program-eyebrow">Praktische informatie</p>
                    <h2>Volg de 24 Uur Live</h2>
                    <p>De planning is voorlopig en kan nog wijzigen. De nieuwste informatie vind je op deze pagina.</p>
                    <ActionButton :href="links.livestream" icon="play">Volg de livestream</ActionButton>
                </div>
                <EventDetails :starts-at="event.startsAt" :ends-at="event.endsAt" :location="event.location" />
            </section>
        </div>
    </div>
</template>
