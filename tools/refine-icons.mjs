import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const file = resolve(root, 'resources/views/components/icons.svg');
let source = readFileSync(file, 'utf8');

// Earlier generated symbols wrapped a second SVG. Keep only path data in each symbol.
source = source
  .replace(/<svg class="lucide[^>]*>\s*/g, '')
  .replace(/<\/svg><\/symbol>/g, '</symbol>');

const symbols = [
  ['calendar-days', '<path d="M8 2v3M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01M16 17h.01"/>'],
  ['barber-mark', '<path d="M4 10c0-5 3.3-8 8-8s8 3 8 8"/><path d="M4 10c3.8-.1 6.2-1.5 8-4 1.9 2.7 4.2 3.9 8 4M5.5 12v2.5c0 1.3 1 2.3 2.3 2.3h.7c.8 3.1 2 5.2 3.5 5.2s2.7-2.1 3.5-5.2h.7c1.3 0 2.3-1 2.3-2.3V12"/><path d="M8.5 14.5c1.3 1.4 2.4 2 3.5 2s2.2-.6 3.5-2M10 19c1.3.8 2.7.8 4 0"/>'],
  ['hair', '<path d="M4 13V9a8 8 0 0 1 16 0v4M4 10c3.4-.2 6.1-1.5 8-4 1.8 2.5 4.6 3.8 8 4"/><path d="M6 11v4c0 4 2.7 7 6 7s6-3 6-7v-4M4 15c0 2-1 4-2 5m18-5c0 2 1 4 2 5"/>'],
  ['beard', '<path d="M4.5 10a7.5 7.5 0 0 1 15 0M5 10v4c0 1.2.7 2.1 2 2.4l1 .2c.8 3 2.2 5.4 4 5.4s3.2-2.4 4-5.4l1-.2c1.3-.3 2-1.2 2-2.4v-4"/><path d="M8 14c1.5 1.2 2.7 1.7 4 1.7s2.5-.5 4-1.7M9 18c2 1 4 1 6 0M4.5 10c3.3.1 5.8-1.2 7.5-3.5 1.7 2.3 4.2 3.6 7.5 3.5"/>'],
  ['comb', '<path d="M4 7h16v4H4zM6 11v7m2-7v5m2-5v7m2-7v5m2-5v7m2-7v5m2-5v7m2-7v5"/>'],
  ['razor', '<path d="M5 4h14v4H5zM8 8v4m8-4v4M8 12h8l1 3H7l1-3Zm4 3v6"/>'],
  ['refresh', '<path d="M20 11a8 8 0 0 0-14.6-4.5L3 9m0-5v5h5M4 13a8 8 0 0 0 14.6 4.5L21 15m0 5v-5h-5"/>'],
];

const added = symbols
  .filter(([id]) => !source.includes(`id="i-${id}"`))
  .map(([id, paths]) => `<symbol id="i-${id}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${paths}</symbol>`)
  .join('');

source = source.replace(/<\/svg>\s*$/, `${added}</svg>\n`);
writeFileSync(file, source, 'utf8');
