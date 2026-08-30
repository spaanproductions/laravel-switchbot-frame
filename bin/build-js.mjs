// Copies the package's JavaScript into dist/ for the standalone page: our own
// interceptor plus the vendored heic2any decoder. There is no JS bundler here
// (the package is otherwise CSS-only), so this is a plain, cross-platform copy
// run as part of `npm run build`.
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const copies = [
	['resources/js/switchbot-frame.js', 'dist/switchbot-frame.js'],
	['node_modules/heic2any/dist/heic2any.min.js', 'dist/heic2any.min.js'],
];

mkdirSync(resolve(root, 'dist'), { recursive: true });

for (const [from, to] of copies) {
	copyFileSync(resolve(root, from), resolve(root, to));
	console.log(`copied ${from} -> ${to}`);
}
