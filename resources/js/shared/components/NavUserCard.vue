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
            <p class="nav-user-card__name">{{ fullName || t('common.user') }}</p>
            <p class="nav-user-card__level">{{ subtitle || t('layout.brandLabel') }}</p>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from '../i18n';
import { FontAwesomeIcon } from '../icons';

const { t } = useI18n();

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
