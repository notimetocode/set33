<template>
    <nav v-if="items.length" class="app-breadcrumbs" aria-label="Навигация по разделам">
        <ol class="breadcrumb mb-0">
            <li
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
                class="breadcrumb-item"
                :class="{ active: index === items.length - 1 }"
                :aria-current="index === items.length - 1 ? 'page' : undefined"
            >
                <RouterLink
                    v-if="item.name && index < items.length - 1"
                    :to="item.params ? { name: item.name, params: item.params } : { name: item.name }"
                >
                    {{ item.label }}
                </RouterLink>
                <span v-else>{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>

<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>
