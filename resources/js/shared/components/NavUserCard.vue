<template>
    <div class="nav-user-card" :class="{ 'nav-user-card--loading': loading }">
        <div class="nav-user-card__avatar" aria-hidden="true">
            <img
                v-if="avatarUrl"
                :src="avatarUrl"
                alt=""
                width="48"
                height="48"
            >
            <span v-else class="nav-user-card__initials">{{ initials }}</span>
        </div>
        <div class="nav-user-card__body">
            <p class="nav-user-card__name">{{ fullName || 'Пользователь' }}</p>
            <p class="nav-user-card__level">{{ subtitle || 'Личный кабинет' }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    avatarUrl: {
        type: String,
        default: '',
    },
    fullName: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const initials = computed(() => {
    const parts = String(props.fullName || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    if (parts.length === 0) {
        return '?';
    }

    return parts.map((part) => part.charAt(0).toUpperCase()).join('');
});
</script>
