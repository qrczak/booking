<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useI18n } from '../composables/useI18n';

const auth = useAuthStore();
const router = useRouter();
const { messages, t } = useI18n();

const mode = ref('login');
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const generalError = ref('');

async function submit() {
    errors.value = {};
    generalError.value = '';
    try {
        if (mode.value === 'login') {
            await auth.login(form.email, form.password);
        } else {
            await auth.register({ ...form });
        }
        router.push({ name: 'rooms' });
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
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow">
        <h1 class="mb-4 text-xl font-semibold">
            {{ mode === 'login' ? messages.auth.login : messages.auth.register }}
        </h1>
        <p v-if="generalError" class="mb-4 rounded bg-red-50 p-2 text-sm text-red-700">{{ generalError }}</p>
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="mode === 'register'">
                <label class="block text-sm font-medium">{{ messages.auth.name }}</label>
                <input v-model="form.name" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ messages.auth.email }}</label>
                <input v-model="form.email" type="email" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.email" class="text-sm text-red-600">{{ errors.email[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ messages.auth.password }}</label>
                <input v-model="form.password" type="password" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.password" class="text-sm text-red-600">{{ errors.password[0] }}</p>
            </div>
            <div v-if="mode === 'register'">
                <label class="block text-sm font-medium">{{ messages.auth.passwordConfirmation }}</label>
                <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.password_confirmation" class="text-sm text-red-600">{{ errors.password_confirmation[0] }}</p>
            </div>
            <button type="submit" class="w-full rounded bg-blue-600 py-2 text-white hover:bg-blue-700">
                {{ mode === 'login' ? messages.auth.login : messages.auth.register }}
            </button>
        </form>
        <button
            class="mt-4 text-sm text-blue-600 hover:underline"
            @click="mode = mode === 'login' ? 'register' : 'login'"
        >
            {{ mode === 'login' ? messages.auth.noAccount : messages.auth.haveAccount }}
        </button>
    </div>
</template>
