<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\DB;
use App\Support\IranMobile;

/**
 * لینک ورود یک‌بارمصرف.
 *
 * چرا لازم است: ورود با کد پیامکی درست‌ترین راه برای این صنف است —
 * رمزی نیست که فراموش شود و شماره را همه همراه دارند. ولی یک وابستگی
 * سخت دارد: **تا پیامک راه نیفتد، هیچ‌کس نمی‌تواند وارد شود.**
 *
 * این یعنی یک مشکل مرغ و تخم‌مرغ در نصب تازه: صاحب سالن باید وارد شود
 * تا سالن را تنظیم کند، ولی برای ورود به پیامکی نیاز دارد که هنوز
 * تنظیم نشده. و اگر روزی حساب اپراتور تمام شود، همه بیرون می‌مانند.
 *
 * پس یک راهِ دوم که **فقط از روی سرور** ساخته می‌شود:
 *
 *     php tools/login-link.php 09121234567
 *
 * کسی که به خط فرمان یا فایل‌منیجر هاست دسترسی دارد، عملاً به کل
 * دیتابیس هم دسترسی دارد؛ پس این هیچ درِ تازه‌ای باز نمی‌کند. آنچه
 * می‌دهد، راهی است برای شروع کار و برای وقتی که پیامک از کار افتاده.
 *
 * از همان جدول otp_codes استفاده می‌کند: مهلت، مصرف‌شدن و پاک‌سازی
 * از قبل آنجا حل شده و دو پیاده‌سازی موازی، دو جای خراب شدن است.
 */
final class LoginLinkService
{
    public const PURPOSE = 'login_link';

    /** کوتاه، چون فقط برای همان لحظه است. */
    private const TTL_SECONDS = 900;

    /**
     * توکن تازه می‌سازد و **متن خام** را برمی‌گرداند.
     *
     * در دیتابیس فقط هَش می‌نشیند: اگر کسی دیتابیس را بخواند، نتواند
     * از آن لینک بسازد.
     *
     * @return array{ok:bool,token:?string,error:?string}
     */
    public function issue(IranMobile $phone): array
    {
        $user = DB::selectOne('SELECT id FROM users WHERE phone = ?', [$phone->e164]);

        if ($user === null) {
            return ['ok' => false, 'token' => null, 'error' => 'کاربری با این شماره ثبت نشده است.'];
        }

        // لینک‌های قبلی همین شماره باطل می‌شوند — دو لینک زنده یعنی دو
        // کلید در گردش.
        DB::update(
            'otp_codes',
            ['consumed_at' => date('Y-m-d H:i:s')],
            'phone = :phone AND purpose = :purpose AND consumed_at IS NULL',
            ['phone' => $phone->e164, 'purpose' => self::PURPOSE]
        );

        $token = bin2hex(random_bytes(24));

        DB::insert('otp_codes', [
            'phone' => $phone->e164,
            'code_hash' => hash('sha256', $token),
            'purpose' => self::PURPOSE,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
        ]);

        return ['ok' => true, 'token' => $token, 'error' => null];
    }

    /**
     * توکن را مصرف می‌کند و شناسهٔ کاربر را برمی‌گرداند.
     *
     * یک‌بارمصرف است: پیش از هر چیز consumed_at را می‌نشاند، پس حتی
     * اگر لینک جایی لو برود، بار دوم کار نمی‌کند.
     */
    public function consume(string $token): ?int
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT id, phone FROM otp_codes
             WHERE code_hash = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > NOW()
             LIMIT 1',
            [hash('sha256', $token), self::PURPOSE]
        );

        if ($row === null) {
            return null;
        }

        DB::update('otp_codes', ['consumed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);

        $user = DB::selectOne('SELECT id FROM users WHERE phone = ?', [$row['phone']]);

        return $user === null ? null : (int) $user['id'];
    }
}
