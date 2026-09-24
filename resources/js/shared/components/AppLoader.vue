<template>
    <div
        class="app-loader"
        :class="[
            `app-loader--${size}`,
            { 'app-loader--block': block },
        ]"
        role="status"
        :aria-busy="true"
        :aria-label="label || 'Загрузка'"
    >
        <span class="app-loader__spinner" aria-hidden="true" />
        <span
            v-if="label"
            class="app-loader__label"
        >
            {{ label }}
        </span>
        <span
            v-else
            class="visually-hidden"
        >
            Загрузка…
        </span>
    </div>
</template>

<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    label: {
        type: String,
        default: '',
    },
    block: {
        type: Boolean,
        default: false,
    },
});
</script>
