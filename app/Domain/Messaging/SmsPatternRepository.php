<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\DB;

/**
 * حافظهٔ الگوهای ثبت‌شده نزد اپراتور.
 *
 * چیزی که این جدول جواب می‌دهد و `.env` نمی‌تواند: «چه متنی ثبت شد و
 * کِی؟». شناسهٔ الگو در `.env` می‌نشیند چون تصمیم‌گیرندهٔ نهایی همان
 * است، ولی تا وقتی اپراتور تأیید نکرده آن شناسه کار نمی‌کند — و صاحب
 * سالن باید بتواند ببیند منتظر چیست.
 */
final class SmsPatternRepository
{
    /**
     * ثبتِ تازه را ذخیره می‌کند، یا ردیف قبلی را به‌روز.
     *
     * جایگزینی عمدی است: اگر متن الگو عوض شود و دوباره ثبت شود،
     * شناسهٔ تازه باید جای قبلی را بگیرد. نگه داشتن هر دو یعنی صاحب
     * سالن نمی‌داند کدام‌یک در `.env` است.
     */
    public function remember(
        string $provider,
        string $code,
        string $bodyId,
        string $title,
        string $body,
        ?int $userId
    ): void {
        DB::statement(
            'INSERT INTO sms_pattern_registrations
                 (provider, template_code, body_id, title, body, registered_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 body_id = VALUES(body_id),
                 title = VALUES(title),
                 body = VALUES(body),
                 registered_by = VALUES(registered_by),
                 registered_at = CURRENT_TIMESTAMP',
            [$provider, $code, $bodyId, $title, $body, $userId]
        );
    }

    /**
     * همهٔ ثبت‌های یک اپراتور، به تفکیک کد الگو.
     *
     * @return array<string,array{body_id:string,title:string,body:string,registered_at:string}>
     */
    public function forProvider(string $provider): array
    {
        $rows = DB::select(
            'SELECT template_code, body_id, title, body, registered_at
               FROM sms_pattern_registrations
              WHERE provider = ?',
            [$provider]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['template_code']] = [
                'body_id' => (string) $row['body_id'],
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
                'registered_at' => (string) $row['registered_at'],
            ];
        }

        return $out;
    }

    public function forget(string $provider, string $code): void
    {
        DB::statement(
            'DELETE FROM sms_pattern_registrations WHERE provider = ? AND template_code = ?',
            [$provider, $code]
        );
    }
}
