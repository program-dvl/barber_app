import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/index.js';
import { i18nVue } from 'laravel-vue-i18n';
import { installMarketingTelemetryContract } from './Support/marketingTelemetry';

installMarketingTelemetryContract();

createInertiaApp({
    title: (title) => {
        const productName = document.querySelector('meta[name="application-name"]')?.content || 'ClipperDesk';

        return title.toLowerCase().includes(productName.toLowerCase()) ? title : `${title} | ${productName}`;
    },
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                resolve: async lang => {
                    const langs = import.meta.glob('../../lang/*.json');
                    return await langs[`../../lang/${lang}.json`]();
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4338CA',
    },
});
