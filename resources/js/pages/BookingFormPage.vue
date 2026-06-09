<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBookingsStore } from '../stores/bookings';
import { useI18n } from '../composables/useI18n';

const store = useBookingsStore();
const route = useRoute();
const router = useRouter();
const { messages, t } = useI18n();

const form = reactive({
    room_id: route.query.room_id ? Number(route.query.room_id) : '',
    starts_at: '',
    ends_at: '',
    participants_count: 1,
});
const errors = ref({});
const generalError = ref('');

onMounted(() => store.fetchRooms());

async function submit() {
    errors.value = {};
    generalError.value = '';
    try {
        await store.createBooking({
            room_id: form.room_id,
            starts_at: form.starts_at,
            ends_at: form.ends_at,
            participants_count: form.participants_count,
        });
        router.push({ name: 'bookings' });
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            generalError.value = error.response.data.message ?? '';
        } else {
            generalError.value = t('app.genericError');
        }
    }
}
</script>

<template>
    <h1 class="mb-4 text-xl font-semibold">{{ messages.bookings.create }}</h1>
    <form class="max-w-md space-y-4 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.room }}</label>
            <select v-model="form.room_id" class="mt-1 w-full rounded border px-3 py-2">
                <option value="" disabled>—</option>
                <option v-for="room in store.rooms" :key="room.id" :value="room.id">
                    {{ room.name }} ({{ room.capacity }})
                </option>
            </select>
            <p v-if="errors.room_id" class="text-sm text-red-600">{{ errors.room_id[0] }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.startsAt }}</label>
            <input v-model="form.starts_at" type="datetime-local" class="mt-1 w-full rounded border px-3 py-2" />
            <p v-if="errors.starts_at" class="text-sm text-red-600">{{ errors.starts_at[0] }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.endsAt }}</label>
            <input v-model="form.ends_at" type="datetime-local" class="mt-1 w-full rounded border px-3 py-2" />
            <p v-if="errors.ends_at" class="text-sm text-red-600">{{ errors.ends_at[0] }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.participants }}</label>
            <input
                v-model.number="form.participants_count"
                type="number"
                min="1"
                class="mt-1 w-full rounded border px-3 py-2"
            />
            <p v-if="errors.participants_count" class="text-sm text-red-600">{{ errors.participants_count[0] }}</p>
        </div>
        <button type="submit" class="w-full rounded bg-blue-600 py-2 text-white hover:bg-blue-700">
            {{ messages.bookings.submit }}
        </button>
    </form>
</template>
