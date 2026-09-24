<template>
    <article class="site-integration-card">
        <div class="site-integration-card__top">
            <h3 class="site-integration-card__title">{{ title }}</h3>
            <span
                class="status-tag"
                :class="connected ? 'status-tag--ok' : 'status-tag--muted'"
            >
                {{ connected ? 'Подключено' : 'Не подключено' }}
            </span>
        </div>

        <p class="site-integration-card__detail">
            <template v-if="connected && detail">{{ detail }}</template>
            <template v-else>{{ emptyDetail }}</template>
        </p>

        <div class="site-integration-card__actions">
            <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="disabled"
                @click="$emit('configure')"
            >
                {{ connected ? 'Настроить' : 'Подключить' }}
            </button>
        </div>
    </article>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    connected: {
        type: Boolean,
        default: false,
    },
    detail: {
        type: String,
        default: '',
    },
    emptyDetail: {
        type: String,
        default: 'Сервис не привязан к сайту',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['configure']);
</script>
