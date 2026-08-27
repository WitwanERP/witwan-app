import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Nombre de la licencia (tenant) del payload inicial de Inertia: por ahora es el
// title de todas las páginas. No cambia entre navegaciones SPA, así que se lee una vez.
const licencia = (() => {
    try {
        return JSON.parse(document.querySelector('[data-page]').dataset.page).props.tenant?.nombre || 'WitWan';
    } catch {
        return 'WitWan';
    }
})();

createInertiaApp({
    title: () => licencia,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('Link', Link)
            .mount(el);
    },
    progress: { color: '#3B82F6' },
});
