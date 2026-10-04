// Usage: node tools/build-service-photos.cjs <folder-containing-category-pngs>
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const categories = ['haircut', 'beard', 'styling', 'facial', 'care', 'color'];
const source = process.argv[2];
if (!source) throw new Error('Provide a folder containing the six named source PNGs.');
const output = path.resolve(__dirname, '../public/assets/images/services');
fs.mkdirSync(output, { recursive: true });
Promise.all(categories.flatMap(category => [240, 640].map(size =>
  sharp(path.resolve(source, category + '.png'))
    .rotate().resize(size, size, { fit: 'cover', position: 'centre' })
    .webp({ quality: 80, effort: 6 })
    .toFile(path.join(output, `${category}-${size}.webp`))
))).then(results => console.log('Built 12 WebP assets:', results.reduce((sum, r) => sum + r.size, 0), 'bytes'))
  .catch(error => { console.error(error); process.exitCode = 1; });
