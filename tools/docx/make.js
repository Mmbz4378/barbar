const H = require('./build.js');
const {
  Document, Packer, Paragraph, TextRun, AlignmentType, LevelFormat,
  Footer, Header, PageNumber, BorderStyle, fs,
} = H;

const c1 = require('./content1.js');
const c2 = require('./content2.js');
const c3 = require('./content3.js');
const c4 = require('./content4.js');
const c5 = require('./content5.js');
const c6 = require('./content6.js');
const c7 = require('./content7.js');

const FONT = H.FONT;

const doc = new Document({
  creator: 'رشن',
  title: 'رشن — سند محصول',
  description: 'سند محصول، تحلیل رقبا، معماری و برنامهٔ اجرا برای سامانهٔ نوبت‌دهی آرایشگاه مردانه',
  styles: {
    default: {
      document: {
        run: { font: FONT, size: 20, color: H.INK },
        paragraph: { bidirectional: true, spacing: { line: 300, after: 120 } },
      },
      heading1: { run: { font: FONT, size: 32, bold: true, color: H.ACC },
                  paragraph: { bidirectional: true, spacing: { before: 0, after: 240 } } },
      heading2: { run: { font: FONT, size: 25, bold: true, color: H.ACC },
                  paragraph: { bidirectional: true, spacing: { before: 320, after: 140 } } },
      heading3: { run: { font: FONT, size: 22, bold: true, color: H.INK },
                  paragraph: { bidirectional: true, spacing: { before: 240, after: 100 } } },
    },
  },
  numbering: {
    config: [
      { reference: 'dots', levels: [
        { level: 0, format: LevelFormat.BULLET, text: '▪', alignment: AlignmentType.RIGHT,
          style: { paragraph: { indent: { right: 460, hanging: 240 } },
                   run: { font: FONT, size: 18, color: H.ACC } } },
        { level: 1, format: LevelFormat.BULLET, text: '–', alignment: AlignmentType.RIGHT,
          style: { paragraph: { indent: { right: 900, hanging: 240 } },
                   run: { font: FONT, size: 18, color: H.DIM } } },
      ]},
      { reference: 'nums', levels: [
        { level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.RIGHT,
          style: { paragraph: { indent: { right: 500, hanging: 280 } },
                   run: { font: FONT, size: 19, bold: true, color: H.ACC } } },
      ]},
    ],
  },
  sections: [{
    properties: {
      page: {
        size: { width: 11906, height: 16838 },          // A4
        margin: { top: 1134, right: 1134, bottom: 1134, left: 1134, header: 680, footer: 567 },
      },
      bidi: true,
      titlePage: true,          // جلد، سربرگ و شمارهٔ صفحه نداشته باشد
    },
    headers: {
      first: new Header({ children: [new Paragraph({ children: [] })] }),
      default: new Header({ children: [new Paragraph({
        bidirectional: true, alignment: AlignmentType.LEFT, spacing: { after: 0 },
        border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: H.LINE, space: 6 } },
        children: [new TextRun({ text: 'رشن  ·  سند محصول',
          rightToLeft: true, font: FONT, size: 15, color: H.DIM })],
      })] }),
    },
    footers: {
      first: new Footer({ children: [new Paragraph({ children: [] })] }),
      default: new Footer({ children: [new Paragraph({
        bidirectional: true, alignment: AlignmentType.CENTER, spacing: { before: 0, after: 0 },
        children: [new TextRun({ children: [PageNumber.CURRENT],
          rightToLeft: true, font: FONT, size: 16, color: H.DIM })],
      })] }),
    },
    children: [
      ...c1.cover, ...c1.toc, ...c1.s1, ...c1.s2,
      ...c2.s3, ...c2.s4,
      ...c3.s5,
      ...c4.s6, ...c4.s7,
      ...c5.s8,
      ...c6.s9, ...c6.s10,
      ...c7.s11, ...c7.app,
    ],
  }],
});

Packer.toBuffer(doc).then(buf => {
  fs.writeFileSync('reshen.docx', buf);
  console.log('نوشته شد: reshen.docx —', (buf.length / 1024).toFixed(0), 'کیلوبایت');
});
