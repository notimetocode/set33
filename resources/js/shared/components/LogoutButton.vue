<template>
    <button class="logout-btn" type="button" @click="$emit('click')">
        <span class="logout-btn__icon" aria-hidden="true">
            <FontAwesomeIcon :icon="['fas', 'right-from-bracket']" />
        </span>
        Выйти
    </button>
</template>

<script setup>
import { FontAwesomeIcon } from '../icons';

defineEmits(['click']);
</script>
