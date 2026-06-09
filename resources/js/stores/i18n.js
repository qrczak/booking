import { defineStore } from 'pinia';
import api from '../lib/axios';

const LOCALE_KEY = 'app_locale';
const VERSION_KEY = 'i18n.version';

function cacheKey(locale) {
    return `i18n.${locale}`;
}

function readCache(locale) {
    try {
        const raw = localStorage.getItem(cacheKey(locale));
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeCache(locale, messages, version) {
    try {
        localStorage.setItem(cacheKey(locale), JSON.stringify(messages));
        localStorage.setItem(VERSION_KEY, version);
        localStorage.setItem(LOCALE_KEY, locale);
    } catch {
        // localStorage unavailable (e.g. private mode) — fall back to in-memory only.
    }
}

export const useI18nStore = defineStore('i18n', {
    state: () => ({
        locale: localStorage.getItem(LOCALE_KEY) || window.__APP_LOCALE__ || 'pl',
        messages: {},
        available: window.__I18N_LOCALES__ || ['pl', 'en'],
    }),
    actions: {
        /**
         * Load the active locale's messages, preferring a fresh localStorage
         * cache (matched against the version stamped into the page) so that
         * warm visits issue zero network requests.
         */
        async load() {
            await this.ensureLocale(this.locale);
        },
        async ensureLocale(locale) {
            const serverVersion = window.__I18N_VERSION__ || null;
            const cachedVersion = localStorage.getItem(VERSION_KEY);
            const cached = readCache(locale);

            if (cached && serverVersion && cachedVersion === serverVersion) {
                this.messages = cached;
                return;
            }

            try {
                const { data } = await api.get('/translations', { params: { locale } });
                this.messages = data.messages;
                writeCache(locale, data.messages, data.version);
            } catch {
                this.messages = cached ?? {};
            }
        },
        async setLocale(locale) {
            if (!this.available.includes(locale) || locale === this.locale) {
                return;
            }
            this.locale = locale;
            localStorage.setItem(LOCALE_KEY, locale);
            await this.ensureLocale(locale);
        },
        t(path, params = {}) {
            const value = path.split('.').reduce((acc, key) => acc?.[key], this.messages);
            if (typeof value !== 'string') {
                return path;
            }
            return value.replace(/:(\w+)/g, (match, key) => (key in params ? params[key] : match));
        },
    },
});
