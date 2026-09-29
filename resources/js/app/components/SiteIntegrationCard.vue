<template>
    <article
        class="site-integration-card"
        :class="{ 'site-integration-card--connected': connected }"
    >
        <span
            class="site-integration-card__status"
            :class="connected ? 'site-integration-card__status--on' : 'site-integration-card__status--off'"
            role="status"
            :aria-label="connected ? 'Подключено' : 'Не подключено'"
            :title="connected ? 'Подключено' : 'Не подключено'"
        />

        <div class="site-integration-card__heading">
            <img
                v-if="logo"
                class="site-integration-card__logo"
                :src="logo"
                :alt="title"
                width="32"
                height="32"
                loading="lazy"
                decoding="async"
            >
            <div class="site-integration-card__copy">
                <h3 class="site-integration-card__title">{{ title }}</h3>
                <p class="site-integration-card__detail">
                    <template v-if="connected && detail">{{ detail }}</template>
                    <template v-else>{{ emptyDetail }}</template>
                </p>
            </div>
        </div>

        <div class="site-integration-card__actions">
            <button
                type="button"
                class="btn btn-secondary btn-sm"
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
    logo: {
        type: String,
        default: '',
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
