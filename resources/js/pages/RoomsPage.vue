<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBookingsStore } from '../stores/bookings';
import { useI18n } from '../composables/useI18n';

const store = useBookingsStore();
const router = useRouter();
const { messages } = useI18n();

onMounted(() => store.fetchRooms());

function book(roomId) {
    router.push({ name: 'bookings.new', query: { room_id: roomId } });
}
</script>

<template>
    <h1 class="mb-4 text-xl font-semibold">{{ messages.rooms.title }}</h1>
    <div class="grid gap-4 sm:grid-cols-2">
        <div v-for="room in store.rooms" :key="room.id" class="rounded-lg bg-white p-4 shadow">
            <h2 class="font-medium">{{ room.name }}</h2>
            <p class="text-sm text-gray-600">{{ messages.rooms.capacity }}: {{ room.capacity }}</p>
            <button
                class="mt-3 rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700"
                @click="book(room.id)"
            >
                {{ messages.rooms.book }}
            </button>
        </div>
    </div>
</template>
