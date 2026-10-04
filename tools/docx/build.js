const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, PageBreak,
  Table, TableRow, TableCell, WidthType, BorderStyle, ShadingType, ShadingType: ST,
  TableOfContents, LevelFormat, PageOrientation, VerticalAlign, PageNumber, Footer, Header,
} = require('docx');

// ── ثابت‌ها ────────────────────────────────────────────────────────────
const FONT = 'Tahoma';           // روی ویندوز و آفیس مک هست و فارسی را درست می‌چیند
const MONO = 'Consolas';
const W    = 9638;               // عرض محتوا در A4 با حاشیهٔ ۲ سانتی‌متر

const INK   = '1C1F24';
const DIM   = '6B7280';
const LINE  = 'D8DCE2';
const ACC   = '0F5C4A';          // سبز عمیق — رنگ تیتر
const ACCBG = 'EAF3F0';
const WARNBG= 'FDF2F2';
const WARN  = '9B2C2C';
const NOTEBG= 'F4F6F8';

// ارقام فارسی برای فهرست‌های شماره‌دار. Word با numFmt استاندارد ارقام
// لاتین می‌دهد، پس شماره را خودمان می‌نویسیم. شمارنده با هر سرتیتر صفر می‌شود.
const FA = '۰۱۲۳۴۵۶۷۸۹';
const faNum = (n) => String(n).replace(/[0-9]/g, (d) => FA[+d]);
let _n = 0;

// ── کمک‌کننده‌ها ──────────────────────────────────────────────────────
const run = (text, o = {}) => new TextRun({
  text, rightToLeft: true, font: o.mono ? MONO : FONT,
  size: o.size ?? 20, bold: o.bold, italics: o.italics,
  color: o.color ?? INK, break: o.break,
});

const p = (text, o = {}) => new Paragraph({
  bidirectional: true,
  alignment: o.align ?? AlignmentType.JUSTIFIED,
  spacing: { before: o.before ?? 0, after: o.after ?? 120, line: o.line ?? 300 },
  indent: o.indent,
  children: Array.isArray(text) ? text : [run(text, o)],
  ...(o.border ? { border: o.border } : {}),
  ...(o.shading ? { shading: { type: ST.CLEAR, fill: o.shading } } : {}),
  ...(o.keepNext ? { keepNext: true } : {}),
  ...(o.pageBreakBefore ? { pageBreakBefore: true } : {}),
});

const h1 = (text) => ((_n = 0), new Paragraph({
  heading: HeadingLevel.HEADING_1, bidirectional: true, pageBreakBefore: true,
  spacing: { before: 0, after: 240 }, keepNext: true,
  children: [new TextRun({ text, rightToLeft: true, font: FONT, size: 32, bold: true, color: ACC })],
  border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: ACC, space: 8 } },
}));

const h2 = (text) => ((_n = 0), new Paragraph({
  heading: HeadingLevel.HEADING_2, bidirectional: true,
  spacing: { before: 320, after: 140 }, keepNext: true,
  children: [new TextRun({ text, rightToLeft: true, font: FONT, size: 25, bold: true, color: ACC })],
}));

const h3 = (text) => ((_n = 0), new Paragraph({
  heading: HeadingLevel.HEADING_3, bidirectional: true,
  spacing: { before: 240, after: 100 }, keepNext: true,
  children: [new TextRun({ text, rightToLeft: true, font: FONT, size: 22, bold: true, color: INK })],
}));

const bullet = (text, o = {}) => new Paragraph({
  bidirectional: true, alignment: AlignmentType.RIGHT,
  numbering: { reference: 'dots', level: o.level ?? 0 },
  spacing: { after: 70, line: 290 },
  children: Array.isArray(text) ? text : [run(text, o)],
});

const num = (text, o = {}) => new Paragraph({
  bidirectional: true,
  alignment: AlignmentType.RIGHT,
  spacing: { after: 70, line: 290 },
  indent: { right: 520, hanging: 320 },
  children: [
    new TextRun({ text: faNum(++_n) + '.' + '\u00A0\u00A0', rightToLeft: true,
                  font: FONT, size: 19, bold: true, color: ACC }),
    ...(Array.isArray(text) ? text : [run(text, o)]),
  ],
});

// جعبهٔ نکته / هشدار
const box = (lines, kind = 'note') => {
  const fill   = kind === 'warn' ? WARNBG : kind === 'acc' ? ACCBG : NOTEBG;
  const stripe = kind === 'warn' ? WARN   : kind === 'acc' ? ACC   : DIM;
  return new Table({
    width: { size: W, type: WidthType.DXA }, columnWidths: [W],
    visuallyRightToLeft: true,
    borders: {
      top:   { style: BorderStyle.NONE }, bottom: { style: BorderStyle.NONE },
      left:  { style: BorderStyle.NONE }, insideHorizontal: { style: BorderStyle.NONE },
      insideVertical: { style: BorderStyle.NONE },
      right: { style: BorderStyle.SINGLE, size: 18, color: stripe },
    },
    rows: [new TableRow({ children: [new TableCell({
      width: { size: W, type: WidthType.DXA },
      shading: { type: ST.CLEAR, fill },
      margins: { top: 140, bottom: 140, left: 200, right: 200 },
      children: lines.map((l, i) => p(l.t ?? l, {
        align: AlignmentType.RIGHT, bold: l.bold, size: 19, after: i === lines.length - 1 ? 0 : 90,
        color: kind === 'warn' ? WARN : INK,
      })),
    })] })],
  });
};

// جدول
const table = (widths, head, rows, o = {}) => {
  const cell = (txt, i, isHead) => new TableCell({
    width: { size: widths[i], type: WidthType.DXA },
    shading: { type: ST.CLEAR, fill: isHead ? ACC : (o.zebra && o.zebra % 2 ? 'F7F8FA' : 'FFFFFF') },
    margins: { top: 90, bottom: 90, left: 120, right: 120 },
    verticalAlign: VerticalAlign.CENTER,
    children: String(txt).split('\n').map((line, k) => p(line, {
      bold: isHead || (o.boldFirstCol && i === 0),
      color: isHead ? 'FFFFFF' : INK,
      size: o.size ?? 18, after: 0,
      align: (o.center && i > 0) ? AlignmentType.CENTER : AlignmentType.RIGHT,
    })),
  });
  return new Table({
    width: { size: W, type: WidthType.DXA }, columnWidths: widths,
    visuallyRightToLeft: true,
    borders: {
      top:    { style: BorderStyle.SINGLE, size: 4, color: LINE },
      bottom: { style: BorderStyle.SINGLE, size: 4, color: LINE },
      left:   { style: BorderStyle.SINGLE, size: 4, color: LINE },
      right:  { style: BorderStyle.SINGLE, size: 4, color: LINE },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 2, color: LINE },
      insideVertical:   { style: BorderStyle.SINGLE, size: 2, color: LINE },
    },
    rows: [
      new TableRow({ tableHeader: true, children: head.map((t, i) => cell(t, i, true)) }),
      ...rows.map((r, ri) => new TableRow({
        children: r.map((t, i) => new TableCell({
          width: { size: widths[i], type: WidthType.DXA },
          shading: { type: ST.CLEAR, fill: ri % 2 ? 'F7F8FA' : 'FFFFFF' },
          margins: { top: 90, bottom: 90, left: 120, right: 120 },
          verticalAlign: VerticalAlign.CENTER,
          children: String(t).split('\n').map(line => p(line, {
            bold: o.boldFirstCol && i === 0, size: o.size ?? 18, after: 0,
            align: (o.center && i > 0) ? AlignmentType.CENTER : AlignmentType.RIGHT,
          })),
        })),
      })),
    ],
  });
};

const spacer = (h = 160) => new Paragraph({ spacing: { after: h }, children: [] });

// بلوک یک‌عرض (نمودار / کد) — چپ‌چین و تک‌فاصله
const pre = (text) => new Table({
  width: { size: W, type: WidthType.DXA }, columnWidths: [W],
  borders: {
    top: { style: BorderStyle.SINGLE, size: 4, color: LINE },
    bottom: { style: BorderStyle.SINGLE, size: 4, color: LINE },
    left: { style: BorderStyle.SINGLE, size: 4, color: LINE },
    right: { style: BorderStyle.SINGLE, size: 4, color: LINE },
    insideHorizontal: { style: BorderStyle.NONE }, insideVertical: { style: BorderStyle.NONE },
  },
  rows: [new TableRow({ children: [new TableCell({
    width: { size: W, type: WidthType.DXA },
    shading: { type: ST.CLEAR, fill: 'FAFBFC' },
    margins: { top: 140, bottom: 140, left: 160, right: 160 },
    children: text.split('\n').map(line => new Paragraph({
      bidirectional: false, alignment: AlignmentType.LEFT,
      spacing: { after: 0, line: 240 },
      children: [new TextRun({ text: line || ' ', font: MONO, size: 15, color: INK })],
    })),
  })] })],
});

module.exports = { faNum, run, p, h1, h2, h3, bullet, num, box, table, spacer, pre,
  FONT, MONO, W, INK, DIM, LINE, ACC, ACCBG,
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, PageBreak,
  Table, TableRow, TableCell, WidthType, BorderStyle, ShadingType: ST,
  TableOfContents, LevelFormat, VerticalAlign, PageNumber, Footer, Header, fs };
