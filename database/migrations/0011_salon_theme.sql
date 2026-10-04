-- پالت رنگی و فونت هر سالن.
--
-- چرا روی جدول سالن و نه در settings JSON: در فاز ۴ (مارکت‌پلیس) لازم
-- می‌شود کارت هر سالن را با رنگ خودش نشان بدهیم، و کوئری روی ستون
-- ساده‌تر و سریع‌تر از استخراج از JSON است.

ALTER TABLE salons
    ADD COLUMN theme VARCHAR(20) NOT NULL DEFAULT 'gold' AFTER cover_path;
