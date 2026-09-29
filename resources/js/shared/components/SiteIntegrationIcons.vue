<template>
    <div
        v-if="items.length"
        class="site-integration-icons"
        role="list"
        aria-label="Подключённые сервисы"
    >
        <span
            v-for="item in items"
            :key="item.key"
            class="site-integration-icons__item"
            :class="{
                'site-integration-icons__item--error': showStatus && item.status === 'error',
            }"
            role="listitem"
        >
            <img
                class="site-integration-icons__logo"
                :src="item.logo"
                :alt="item.label"
                :title="iconTitle(item)"
                width="24"
                height="24"
                loading="lazy"
                decoding="async"
            >
        </span>
    </div>
    <span v-else class="site-integration-icons__empty text-muted">—</span>
</template>

<script setup>
import { computed } from 'vue';
import { connectedIntegrationsForSite, integrationIconTitle } from '../siteIntegrations';

const props = defineProps({
    site: {
        type: Object,
        required: true,
    },
    showStatus: {
        type: Boolean,
        default: true,
    },
});

const items = computed(() => connectedIntegrationsForSite(props.site));

function iconTitle(item) {
    if (!props.showStatus) {
        return item.label;
    }

    return integrationIconTitle(item);
}
</script>
