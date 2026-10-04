#!/usr/bin/env bash
# آیا کد روی کمترین نسخهٔ PHP که ادعا می‌کنیم اجرا می‌شود؟
#
# چرا این اسکریپت لازم شد: یک بار `new Builder(...)->build()` نوشته شد
# — سینتکس PHP 8.4. روی این ماشین (۸.۴) `php -l` هیچ نگفت، تست‌ها سبز
# بودند، بسته ساخته شد، و روی هاست مشتری با PHP 8.1 برنامه پیش از
# اجرای اولین خط مُرد. `php -l` فقط با مفسری که دارید می‌سنجد، پس
# برای سنجیدن نسخه‌ای که ندارید بی‌فایده است.
#
# PHPCompatibility این کار را می‌کند: سینتکس و توابع را با نسخهٔ هدف
# می‌سنجد، مستقل از مفسر جاری.
#
#   ./tools/check-php-compat.sh            → هدف را از composer.json می‌خواند
#   ./tools/check-php-compat.sh 8.1        → هدف دستی
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT=$(pwd)

TARGET="${1:-}"
if [ -z "$TARGET" ]; then
    TARGET=$(php -r '$c=json_decode(file_get_contents("composer.json"),true);
        preg_match("/(\d+\.\d+)/", $c["require"]["php"] ?? ">=8.1", $m);
        echo $m[1] ?? "8.1";')
fi

TOOLS="${RESHEN_COMPAT_HOME:-$HOME/.cache/reshen-phpcompat}"
PHPCS="$TOOLS/vendor/bin/phpcs"

if [ ! -x "$PHPCS" ]; then
    echo "→ نصب PHPCompatibility در $TOOLS (یک‌بار، نیاز به اینترنت)"
    mkdir -p "$TOOLS"
    (
        cd "$TOOLS"
        composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true >/dev/null 2>&1 || true
        composer require --dev --no-interaction --quiet \
            "squizlabs/php_codesniffer:^4.0" \
            "phpcompatibility/php-compatibility:dev-develop"
    ) || {
        echo "   نصب نشد (اینترنت؟). بررسی سازگاری انجام نشد." >&2
        exit 2
    }
    "$PHPCS" --config-set installed_paths \
        "$TOOLS/vendor/phpcompatibility/php-compatibility,$TOOLS/vendor/phpcsstandards/phpcsutils" >/dev/null
fi

# دروازه‌های تشخیص جدا سنجیده می‌شوند، با نسخه‌ای خیلی قدیمی‌تر.
#
# چرا: این دو صفحه تنها چیزی هستند که وقتی برنامه بالا نمی‌آید کاربر
# دارد. اگر خودشان روی نسخهٔ PHP هاست پارس نشوند، به‌جای پیام راهنما
# «HTTP ERROR 500» می‌دهند — و کاربرِ بدون SSH هیچ سرنخی ندارد. پس
# نباید فقط با کمترین نسخهٔ *ما* بسازند، باید با هر چیزی بسازند.
GATES="public/install.php public/doctor.php"
GATE_TARGET=5.6
echo "→ بررسی دروازه‌های تشخیص با PHP $GATE_TARGET"
if ! "$PHPCS" -q --standard=PHPCompatibility \
        --runtime-set testVersion "${GATE_TARGET}-${GATE_TARGET}" \
        --report=full $GATES; then
    echo >&2
    echo "دروازهٔ تشخیص روی PHP قدیمی پارس نمی‌شود — یعنی روی هاستی که" >&2
    echo "مشکل دارد، خودش هم می‌میرد و چیزی به کاربر نمی‌گوید." >&2
    exit 1
fi
echo "   روی PHP $GATE_TARGET هم بالا می‌آیند."

echo "→ بررسی سازگاری با PHP $TARGET"
# بازهٔ بسته (‎8.1-8.1‎) عمدی است: بازهٔ باز «‎8.1-‎» هشدارهای نسخه‌های
# آینده (۸.۵ و ۸.۶) را هم می‌دهد که به اجرا شدن روی هاست مشتری ربطی
# ندارند و فقط نویز می‌سازند.
"$PHPCS" -q --standard=PHPCompatibility \
    --runtime-set testVersion "${TARGET}-${TARGET}" \
    --extensions=php \
    --ignore='*/vendor/*,*/node_modules/*,*/dist/*,*/storage/*' \
    --report=full "$ROOT" && {
        echo "   بدون ایراد — کد روی PHP $TARGET پارس و اجرا می‌شود."
        exit 0
    }

echo >&2
echo "این کد روی PHP $TARGET اجرا نمی‌شود. بالا را درست کنید." >&2
exit 1
