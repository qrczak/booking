<script setup>
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import { useI18n } from './composables/useI18n';

const auth = useAuthStore();
const router = useRouter();
const { messages, locale, availableLocales, setLocale } = useI18n();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <nav class="bg-white shadow">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                <span class="font-semibold">{{ messages.app?.title }}</span>
                <div class="flex items-center gap-4">
                    <template v-if="auth.isAuthenticated">
                        <RouterLink to="/rooms" class="hover:underline">{{ messages.rooms?.title }}</RouterLink>
                        <RouterLink to="/bookings" class="hover:underline">{{ messages.bookings?.title }}</RouterLink>
                    </template>
                    <select
                        :value="locale"
                        class="rounded border px-2 py-1 text-sm"
                        @change="setLocale($event.target.value)"
                    >
                        <option v-for="code in availableLocales" :key="code" :value="code">
                            {{ code.toUpperCase() }}
                        </option>
                    </select>
                    <button
                        v-if="auth.isAuthenticated"
                        class="text-red-600 hover:underline"
                        @click="logout"
                    >
                        {{ messages.app?.logout }}
                    </button>
                </div>
            </div>
        </nav>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <RouterView />
        </main>
    </div>
</template>
