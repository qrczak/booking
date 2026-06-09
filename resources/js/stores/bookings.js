import { defineStore } from 'pinia';
import api from '../lib/axios';

export const useBookingsStore = defineStore('bookings', {
    state: () => ({
        rooms: [],
        bookings: [],
    }),
    actions: {
        async fetchRooms() {
            const { data } = await api.get('/rooms');
            this.rooms = data.data;
        },
        async fetchBookings() {
            const { data } = await api.get('/bookings');
            this.bookings = data.data;
        },
        async createBooking(payload) {
            await api.post('/bookings', payload);
            await this.fetchBookings();
        },
        async cancelBooking(id) {
            await api.patch(`/bookings/${id}/cancel`);
            await this.fetchBookings();
        },
    },
});
