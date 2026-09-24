<template>
    <div
        class="app-markdown"
        v-html="html"
    />
</template>

<script setup>
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps({
    source: {
        type: String,
        default: '',
    },
});

marked.setOptions({
    gfm: true,
    breaks: true,
});

const html = computed(() => {
    const raw = props.source?.trim() ? marked.parse(props.source) : '';

    return DOMPurify.sanitize(typeof raw === 'string' ? raw : '', {
        USE_PROFILES: { html: true },
    });
});
</script>
