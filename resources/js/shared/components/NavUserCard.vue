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
            <span v-else class="nav-user-card__icon-placeholder">
                <FontAwesomeIcon :icon="['fas', 'user']" />
            </span>
        </div>
        <div class="nav-user-card__body">
            <p class="nav-user-card__name">{{ fullName || 'Пользователь' }}</p>
            <p class="nav-user-card__level">{{ subtitle || 'Личный кабинет' }}</p>
        </div>
    </div>
</template>

<script setup>
import { FontAwesomeIcon } from '../icons';

defineProps({
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
</script>
