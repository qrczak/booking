import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useI18nStore } from './stores/i18n';

const app = createApp(App);

app.use(createPinia());

// Load translations (from cache or one fetch) before the first paint so the UI
// never flashes untranslated keys.
const i18n = useI18nStore();
i18n.load().finally(() => {
    app.use(router).mount('#app');
});
