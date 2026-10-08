<template>
    <Transition name="app-block-process-overlay">
        <div
            v-if="active"
            class="app-block-process-overlay"
            role="status"
            aria-live="polite"
            :aria-busy="true"
            :aria-label="title || message || t('shared.loader.aria')"
        >
            <div class="app-block-process-overlay__card">
                <span
                    class="app-block-process-overlay__spinner"
                    aria-hidden="true"
                />
                <p
                    v-if="title"
                    class="app-block-process-overlay__title"
                >
                    {{ title }}
                </p>
                <p
                    v-if="message"
                    class="app-block-process-overlay__message"
                >
                    {{ message }}
                </p>
                <span
                    v-else-if="!title"
                    class="visually-hidden"
                >
                    {{ t('common.loading') }}
                </span>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { useI18n } from '../i18n';

const { t } = useI18n();

defineProps({
    active: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    message: {
        type: String,
        default: '',
    },
});
</script>
