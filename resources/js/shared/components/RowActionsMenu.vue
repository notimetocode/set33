<template>
    <div ref="rootEl" class="row-actions-menu">
        <button
            ref="triggerEl"
            type="button"
            class="row-actions-menu__trigger"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-label="Действия"
            :disabled="disabled"
            @click.stop="toggle"
        >
            <FontAwesomeIcon :icon="['fas', 'ellipsis-vertical']" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="panelEl"
                class="row-actions-menu__panel"
                role="menu"
                :style="panelStyle"
                @click.stop
            >
                <slot :close="close" />
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { FontAwesomeIcon } from '../icons';

defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});

const open = ref(false);
const rootEl = ref(null);
const triggerEl = ref(null);
const panelEl = ref(null);
const panelStyle = ref({});

function close() {
    open.value = false;
}

async function toggle() {
    open.value = !open.value;

    if (open.value) {
        await nextTick();
        updatePosition();
        await nextTick();
        updatePosition();
    }
}

function updatePosition() {
    const trigger = triggerEl.value;

    if (!trigger) {
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const gap = 4;
    const estimatedHeight = panelEl.value?.offsetHeight ?? 88;
    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const openUpward = spaceBelow < estimatedHeight && rect.top > spaceBelow;

    panelStyle.value = {
        position: 'fixed',
        top: openUpward
            ? `${Math.max(8, rect.top - estimatedHeight - gap)}px`
            : `${rect.bottom + gap}px`,
        left: 'auto',
        right: `${Math.max(8, window.innerWidth - rect.right)}px`,
        zIndex: 1080,
    };
}

function onDocumentClick(event) {
    if (!open.value) {
        return;
    }

    const target = event.target;
    const inTrigger = rootEl.value?.contains(target);
    const inPanel = panelEl.value?.contains(target);

    if (!inTrigger && !inPanel) {
        close();
    }
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        close();
    }
}

function onViewportChange() {
    if (open.value) {
        updatePosition();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});

defineExpose({ close });
</script>
