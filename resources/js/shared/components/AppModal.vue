<template>
    <Teleport to="body">
        <Transition name="app-modal">
            <div
                v-if="open"
                class="app-modal"
            >
                <div
                    class="modal-backdrop fade show"
                    @click="onBackdrop"
                />
                <div
                    class="modal fade show d-block"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    @keydown.esc.prevent="close"
                >
                    <div
                        class="modal-dialog modal-dialog-centered"
                        :class="dialogClass"
                    >
                        <div class="modal-content">
                            <div
                                class="app-modal__panel"
                                :class="panelClass"
                            >
                                <button
                                    type="button"
                                    class="btn-close app-modal__close"
                                    aria-label="Закрыть"
                                    @click="close"
                                />

                                <div
                                    v-if="variant"
                                    class="app-modal__icon"
                                    aria-hidden="true"
                                >
                                    <FontAwesomeIcon
                                        :icon="variant === 'success' ? ['fas', 'check'] : ['fas', 'xmark']"
                                    />
                                </div>

                                <h2
                                    :id="titleId"
                                    class="app-modal__title"
                                >
                                    {{ title }}
                                </h2>

                                <p
                                    v-if="message"
                                    class="app-modal__message"
                                >
                                    {{ message }}
                                </p>

                                <div
                                    v-if="$slots.default"
                                    class="app-modal__slot"
                                >
                                    <slot />
                                </div>

                                <div
                                    v-if="hasActionsSlot || shouldShowDefaultConfirm"
                                    class="app-modal__actions"
                                    :class="{ 'app-modal__actions--split': hasActionsSlot }"
                                >
                                    <slot name="actions">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            @click="close"
                                        >
                                            {{ confirmLabel }}
                                        </button>
                                    </slot>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, useSlots, watch } from 'vue';
import { FontAwesomeIcon } from '../icons';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: '',
        validator: (value) => ['', 'success', 'danger'].includes(value),
    },
    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    align: {
        type: String,
        default: 'center',
        validator: (value) => ['center', 'start'].includes(value),
    },
    confirmLabel: {
        type: String,
        default: 'Понятно',
    },
    showConfirm: {
        type: Boolean,
        default: true,
    },
    closeOnBackdrop: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'update:open']);
const slots = useSlots();

const titleId = `app-modal-title-${Math.random().toString(36).slice(2, 9)}`;

const dialogClass = computed(() => {
    if (props.size === 'lg') {
        return 'modal-lg';
    }

    if (props.size === 'md') {
        return null;
    }

    return 'modal-sm';
});

const panelClass = computed(() => [
    props.variant ? `app-modal__panel--${props.variant}` : null,
    props.align === 'start' ? 'app-modal__panel--start' : null,
]);

const hasActionsSlot = computed(() => Boolean(slots.actions));
const shouldShowDefaultConfirm = computed(() => props.showConfirm && !hasActionsSlot.value);

function close() {
    emit('update:open', false);
    emit('close');
}

function onBackdrop() {
    if (props.closeOnBackdrop) {
        close();
    }
}

function syncBodyScroll(isOpen) {
    document.body.classList.toggle('modal-open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

watch(
    () => props.open,
    (isOpen) => {
        syncBodyScroll(isOpen);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    syncBodyScroll(false);
});
</script>
