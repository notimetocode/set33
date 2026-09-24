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
                    aria-label="Закрыть"
                    @click="dismiss(item.id)"
                />
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { dismiss, toasts } from '../toast';
</script>
