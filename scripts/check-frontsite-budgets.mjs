import { readFile, stat, readdir } from 'node:fs/promises';
import { join } from 'node:path';

const root = new URL('../', import.meta.url).pathname;
const manifest = JSON.parse(await readFile(join(root, 'public/build/manifest.json'), 'utf8'));
const limits = { coreJs: 450 * 1024, css: 300 * 1024, routeJs: 160 * 1024, font: 130 * 1024, image: 550 * 1024 };
const failures = [];
const size = async (relative) => (await stat(join(root, 'public/build', relative))).size;
const core = manifest['resources/js/app.js'];
if (await size(core.file) > limits.coreJs) failures.push(`Core JS exceeds ${limits.coreJs} bytes`);
for (const css of core.css ?? []) if (await size(css) > limits.css) failures.push(`${css} exceeds the public CSS budget`);

const publicEntries = Object.entries(manifest).filter(([key]) => /resources\/js\/Pages\/(Home|Blog|Article|Error|TermsOfService|PrivacyPolicy|Marketing\/)/.test(key));
for (const [key, entry] of publicEntries) if (entry.file && await size(entry.file) > limits.routeJs) failures.push(`${key} exceeds the route JS budget`);

for (const file of await readdir(join(root, 'public/fonts/good-hours'))) {
    if (/\.(ttf|woff2)$/.test(file) && (await stat(join(root, 'public/fonts/good-hours', file))).size > limits.font) failures.push(`${file} exceeds the font-file budget`);
}
for (const entry of publicEntries.map(([, value]) => value)) for (const asset of entry.assets ?? []) if (await size(asset) > limits.image) failures.push(`${asset} exceeds the public image budget`);

if (failures.length) {
    failures.forEach((failure) => console.error(failure));
    process.exit(1);
}
console.log(`Frontsite budgets passed for ${publicEntries.length} route entries.`);
