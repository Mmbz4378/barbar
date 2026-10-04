const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');

const root = path.resolve(__dirname, '../public/assets/icons');
const renders = [
  ['icon.svg', 'icon-192.png', 192],
  ['icon.svg', 'icon-512.png', 512],
  ['icon.svg', 'apple-touch-icon.png', 180],
  ['icon-maskable.svg', 'icon-maskable-192.png', 192],
  ['icon-maskable.svg', 'icon-maskable-512.png', 512],
];

Promise.all(renders.map(([input, output, size]) =>
  sharp(fs.readFileSync(path.join(root, input)))
    .resize(size, size)
    .png()
    .toFile(path.join(root, output))
)).catch(error => { console.error(error); process.exitCode = 1; });
