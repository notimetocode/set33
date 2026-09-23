<template>
    <RouterView v-slot="{ Component, route }">
        <Transition name="page-fade" mode="out-in">
            <component :is="Component" :key="route.matched[0]?.path ?? route.path" />
        </Transition>
    </RouterView>
</template>

<script setup>
</script>
