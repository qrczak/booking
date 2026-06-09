import { computed } from 'vue';
import { useI18nStore } from '../stores/i18n';

/**
 * Thin facade over the i18n Pinia store. `messages` is the reactive UI message
 * tree (templates: `messages.auth.login`); `t()` resolves a dot-path with
 * `:placeholder` substitution for programmatic use.
 */
export function useI18n() {
    const store = useI18nStore();

    return {
        messages: computed(() => store.messages),
        locale: computed(() => store.locale),
        availableLocales: store.available,
        setLocale: (locale) => store.setLocale(locale),
        t: (path, params) => store.t(path, params),
    };
}
