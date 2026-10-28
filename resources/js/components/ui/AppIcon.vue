<script setup>
defineProps({ name: { type: String, required: true } });

const icons = {
    heart: ['M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z'],
    game: ['M6 7.5h12a4 4 0 0 1 3.8 3l1.4 6.1a2.2 2.2 0 0 1-3.4 2.3l-3.2-2.4H7.4l-3.2 2.4A2.2 2.2 0 0 1 .8 16.6l1.4-6.1A4 4 0 0 1 6 7.5Z', 'M7 10.5v5m-2.5-2.5h5m6.5-1.5h.01m2.3 2.1h.01'],
    play: ['m8 4 12 8-12 8V4Z'],
    handshake: ['m3 10 5-5 4 1 3-1 6 5-8 8a2 2 0 0 1-2.9 0L5 13', 'm2 9 4 4m16-4-4 4m-9-3 3 3m-1 2 2 2'],
    map: ['M12 22s7-7.4 7-13A7 7 0 0 0 5 9c0 5.6 7 13 7 13Z', 'M14.2 9a2.2 2.2 0 1 1-4.4 0 2.2 2.2 0 0 1 4.4 0'],
    calendar: ['M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z', 'M7 3v4m10-4v4M3 10h18M7 14h.01M12 14h.01M17 14h.01M7 18h.01M12 18h.01'],
    gift: ['M3 9h18v12H3z', 'M12 9v12M2 9h20M12 9H8a3 3 0 1 1 3-3l1 3Zm0 0h4a3 3 0 1 0-3-3l-1 3Z'],
    lock: ['M5 10h14v11H5z', 'M8 10V7a4 4 0 1 1 8 0v3M12 14v3'],
    trophy: ['M8 3h8v8a4 4 0 0 1-8 0V3ZM8 5H3v2a5 5 0 0 0 5 5m8-7h5v2a5 5 0 0 1-5 5M12 15v5m-4 1h8'],
    users: ['M12 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0M2 20v-2a5 5 0 0 1 10 0v2M15 5a3 3 0 0 1 0 6m2 4a4 4 0 0 1 5 4v1'],
    arrow: ['M4 12h16m-7-7 7 7-7 7'],
    check: ['m4 12 5 5L20 6'],
    menu: ['M4 6h16M4 12h16M4 18h16'],
    close: ['M5 5 19 19M19 5 5 19'],
    youtube: ['M6 5h12a4 4 0 0 1 4 4v6a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V9a4 4 0 0 1 4-4Z', 'm10 9 6 3-6 3V9Z'],
    instagram: ['M8 3h8a5 5 0 0 1 5 5v8a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5V8a5 5 0 0 1 5-5ZM16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0M17.5 6.5h.01'],
    twitch: ['M5 3h16v12l-5 5h-5l-3 3v-3H3V7l2-4Z', 'M10 8v5m5-5v5'],
    shield: ['m12 2 9 4v5c0 5-3 9-9 11-6-2-9-6-9-11V6l9-4Z', 'm8 12 3 3 5-6'],
    mail: ['M2 4h20v16H2z', 'm2 6 10 8L22 6'],
    building: ['M4 4h16v18H4z', 'M2 22h20M8 8h2m4 0h2m-8 5h2m4 0h2m-4 9v-5M10 2h4'],
    external: ['M13 4h7v7m-9 2 9-9M20 14v6H4V4h6'],
};
</script>

<template>
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path v-for="(path, index) in icons[name] || []" :key="index" :d="path" />
    </svg>
</template>
