#!/usr/bin/env node
/**
 * Verifies WCAG contrast of every Reshen palette and neutral role.
 *
 *   node tools/check-contrast.mjs
 *
 * Reads public/assets/css/reshen.css directly, so the check can never drift
 * from what ships. Exits non-zero if any pair is below its threshold.
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const css = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../public/assets/css/reshen.css'), 'utf8');

function vars(block) {
  const out = {};
  for (const m of block.matchAll(/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})/g)) out[m[1]] = m[2];
  return out;
}
function blocks(selectorRe) {
  const out = [];
  for (const m of css.matchAll(new RegExp(selectorRe.source + '\\s*\\{([^}]*)\\}', 'g'))) out.push({ sel: m[0].split('{')[0].trim(), vars: vars(m[m.length - 1]) });
  return out;
}
function lum(hex) {
  let h = hex.replace('#', '');
  if (h.length === 3) h = h.split('').map((c) => c + c).join('');
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
function ratio(a, b) {
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
}

// Resolve neutral roles (primitives referenced via var()).
const rootBlocks = [...css.matchAll(/:root\s*\{([^}]*)\}/g)].map((m) => m[1]).join(';');
const prim = vars(rootBlocks);
const resolve = (block) => {
  const out = { ...vars(block) };
  for (const m of block.matchAll(/--([a-z0-9-]+)\s*:\s*var\(--([a-z0-9-]+)\)/g)) if (prim[m[2]]) out[m[1]] = prim[m[2]];
  return out;
};
const light = resolve(rootBlocks);
const darkBlock = css.match(/html\.dark\s*\{([^}]*)\}/)[1];
const dark = { ...light, ...resolve(darkBlock) };

let failures = 0;
function check(label, fg, bg, min) {
  const r = ratio(fg, bg);
  const ok = r >= min;
  if (!ok) failures++;
  console.log(`${ok ? '✓' : '✗'} ${label.padEnd(52)} ${r.toFixed(2).padStart(5)} (≥${min})`);
}

for (const [mode, t] of [['light', light], ['dark', dark]]) {
  console.log(`\n— neutral (${mode})`);
  check(`text on surface`, t.text, t.surface, 7);
  check(`text on bg`, t.text, t.bg, 7);
  check(`text-muted on surface`, t['text-muted'], t.surface, 4.5);
  check(`text-muted on bg`, t['text-muted'], t.bg, 4.5);
  check(`text-muted on sunken`, t['text-muted'], t['surface-sunken'], 4.5);
  check(`text-subtle on surface (non-essential)`, t['text-subtle'], t.surface, 4.5);
  check(`border-input on surface (WCAG 1.4.11)`, t['border-input'], t.surface, 3);
  check(`border-input on bg (WCAG 1.4.11)`, t['border-input'], t.bg, 3);
  for (const s of ['success', 'warning', 'danger', 'info']) {
    check(`${s}-text on ${s}-bg`, t[`${s}-text`], t[`${s}-bg`], 4.5);
    check(`${s}-text on surface`, t[`${s}-text`], t.surface, 4.5);
    check(`on-${s} on ${s}-solid`, t[`on-${s}`], t[`${s}-solid`], 4.5);
  }
}

const themes = blocks(/(?:^|\n)\[data-theme=([a-z]+)\]/);
const darkThemes = blocks(/html\.dark\[data-theme=([a-z]+)\],html\.dark \[data-theme=[a-z]+\]/);
for (const { sel, vars: v } of themes) {
  const name = sel.match(/data-theme=([a-z]+)/)[1];
  console.log(`\n— ${name} (light)`);
  check('on-accent on accent', v['on-accent'], v.accent, 4.5);
  check('on-accent on accent-hover', v['on-accent'], v['accent-hover'], 4.5);
  check('accent-text on surface', v['accent-text'], light.surface, 4.5);
  check('accent-text on bg', v['accent-text'], light.bg, 4.5);
  check('accent-text on accent-soft', v['accent-text'], v['accent-soft'], 4.5);
  check('text on accent-soft', light.text, v['accent-soft'], 7);
}
for (const { sel, vars: v } of darkThemes) {
  const name = sel.match(/data-theme=([a-z]+)/)[1];
  console.log(`\n— ${name} (dark)`);
  check('on-accent on accent', v['on-accent'], v.accent, 4.5);
  check('on-accent on accent-hover', v['on-accent'], v['accent-hover'], 4.5);
  check('accent-text on surface', v['accent-text'], dark.surface, 4.5);
  check('accent-text on bg', v['accent-text'], dark.bg, 4.5);
  check('accent-text on accent-soft', v['accent-text'], v['accent-soft'], 4.5);
  check('text on accent-soft', dark.text, v['accent-soft'], 7);
}

console.log(failures ? `\n${failures} pair(s) below threshold.` : '\nAll pairs pass.');
process.exit(failures ? 1 : 0);
