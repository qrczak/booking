<script setup>
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useBookingsStore } from '../stores/bookings';
import { useI18n } from '../composables/useI18n';
import { useI18nStore } from '../stores/i18n';

const store = useBookingsStore();
const { messages } = useI18n();
const i18nStore = useI18nStore();

onMounted(() => store.fetchBookings());

function formatDate(value) {
    //console.log(i18nStore.locale);
    if (i18nStore.locale === 'pl') {
        return new Date(value).toLocaleString('pl-PL', { dateStyle: 'short', timeStyle: 'short' }).replace(',', '');
    }
    return new Date(value).toLocaleString();
}
</script>

<template>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ messages.bookings.title }}</h1>
        <RouterLink to="/bookings/new" class="rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700">
            {{ messages.bookings.create }}
        </RouterLink>
    </div>
    <p v-if="store.bookings.length === 0" class="text-gray-600">{{ messages.bookings.empty }}</p>
    <div v-else class="space-y-3">
        <div
            v-for="booking in store.bookings"
            :key="booking.id"
            class="flex items-center justify-between rounded-lg bg-white p-4 shadow"
        >
            <div>
                <p class="font-medium">{{ booking.room?.name }}</p>
                <p class="text-sm text-gray-600">{{ formatDate(booking.starts_at) }} – {{ formatDate(booking.ends_at) }}</p>
                <p class="text-sm text-gray-600">{{ messages.bookings.participants }}: {{ booking.participants_count }}</p>
                <p class="text-sm text-gray-600">{{ messages.bookings.status }}: <span :class="booking.status.color" class="rounded-lg px-2 py-1">{{ booking.status.label }}</span></p>
            </div>
            <button
                v-if="booking.status.value !== 'cancelled'"
                class="rounded bg-red-600 px-3 py-1 text-sm text-white hover:bg-red-700"
                @click="store.cancelBooking(booking.id)"
            >
                {{ messages.bookings.cancel }}
            </button>
        </div>
    </div>
</template>
