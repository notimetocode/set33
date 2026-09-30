<template>
    <div
        class="app-loader"
        :class="[
            `app-loader--${size}`,
            { 'app-loader--block': block },
        ]"
        role="status"
        :aria-busy="true"
        :aria-label="label || t('shared.loader.aria')"
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
            {{ t('common.loading') }}
        </span>
    </div>
</template>

<script setup>
import { useI18n } from '../i18n';

const { t } = useI18n();

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
