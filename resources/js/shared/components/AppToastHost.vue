<template>
    <div
        class="app-toast-host"
        aria-live="polite"
        aria-relevant="additions text"
    >
        <TransitionGroup name="app-toast">
            <div
                v-for="item in toasts"
                :key="item.id"
                class="app-toast"
                :class="item.ok ? 'app-toast--ok' : 'app-toast--error'"
                role="status"
            >
                <p class="app-toast__message">{{ item.message }}</p>
                <button
                    type="button"
                    class="btn-close app-toast__close"
                    :aria-label="t('common.close')"
                    @click="dismiss(item.id)"
                />
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { useI18n } from '../i18n';
import { dismiss, toasts } from '../toast';

const { t } = useI18n();
</script>
