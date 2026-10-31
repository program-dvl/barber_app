import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { bookingTheme } from '../../resources/js/Support/bookingTheme.js';

function luminance(hex) {
    const values = hex.slice(1).match(/../g).map(value => parseInt(value, 16) / 255)
        .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    return values[0] * 0.2126 + values[1] * 0.7152 + values[2] * 0.0722;
}
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + 0.05) / (Math.min(luminance(a), luminance(b)) + 0.05);

test('saved accents retain their exact decorative colour with readable actions across light booking surfaces', () => {
    for (const color of ['#F06445', '#172554', '#ffffff', '#000000', '#ffff00', '#00ffff', '#00ff00', '#ff00ff', '#fefefe', '#6D4AFF']) {
        const theme = bookingTheme(color);
        assert.equal(theme['--booking-accent'], color.toLowerCase());
        for (const surface of ['#ffffff', '#eef2f7', theme['--status-info-soft']]) {
            assert.ok(contrast(theme['--action-primary'], surface) >= 4.5, `${color} is readable on ${surface}`);
        }
        assert.ok(contrast(theme['--action-primary-hover'], '#ffffff') >= 4.5);
        assert.ok(contrast(theme['--action-primary'], '#ffffff') >= 4.5);
        assert.deepEqual(Object.keys(theme).filter(key => /danger|warning|success|brand-primary/.test(key)), []);
    }
});

test('absent and malformed saved values inherit the platform theme without accepting CSS injection', () => {
    for (const value of [null, undefined, '', '#abc', 'orange', '#123456;', '#123456; background:url(https://example.test)', {}, 123]) {
        assert.deepEqual(bookingTheme(value), {});
    }
});

async function actualLayout() {
    const { descriptor } = parse(await readFile(resolve('resources/js/Layouts/PublicBookingLayout.vue'), 'utf8'));
    let code = compileScript(descriptor, { id: 'booking-theme-test', inlineTemplate: true }).content;
    code = code.replace(/from (['"])vue\1/g, `from '${pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href}'`)
        .replace(/import \{ Head, Link, router \} from '@inertiajs\/vue3';/, 'const Head={render(){return null}}, Link={render(){return null}}, router={};')
        .replace(/import \{ LockClosedIcon \} from '@heroicons\/vue\/24\/outline';/, 'const LockClosedIcon={render(){return null}};')
        .replace(/import (ProductMark|BookingSkeleton) from '[^']+';/g, 'const $1={render(){return null}};')
        .replace("from '@/Support/bookingTheme';", `from '${pathToFileURL(resolve('resources/js/Support/bookingTheme.js')).href}';`);
    return (await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
}

test('the actual public layout scopes business branding to its root for both public and private preview pages', async () => {
    const Layout = await actualLayout();
    for (const preview of [false, true]) {
        const app = createSSRApp({ render: () => h('div', [
            h(Layout, { title: 'Orange Studio', currentStep: 1, preview, brandColor: '#F06445' }),
            h(Layout, { title: 'Default Studio', currentStep: 1, preview }),
        ]) });
        app.config.globalProperties.route = () => '/';
        const html = await renderToString(app);
        assert.equal((html.match(/--booking-accent:#f06445/g) || []).length, 1);
        assert.ok(html.includes(`--action-primary:${bookingTheme('#F06445')['--action-primary']}`));
        assert.ok(html.includes('var(--booking-accent,var(--action-primary))'));
        assert.equal((html.match(/class="cd-product-flow/g) || []).length, 2);
    }
});
