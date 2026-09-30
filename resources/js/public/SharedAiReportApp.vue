<template>
    <div class="page-shared-ai-report__content">
        <AppAiReportBody
            :source="source"
            :charts="charts"
        />
    </div>
</template>

<script setup>
import AppAiReportBody from '../shared/components/AppAiReportBody.vue';

defineProps({
    source: {
        type: String,
        default: '',
    },
    charts: {
        type: Array,
        default: () => [],
    },
});
</script>
