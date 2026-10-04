<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\SalonRepository;
use App\Domain\Salon\SalonStats;
use App\Support\ImageUpload;

/**
 * معرفی عمومی سالن در «کشف» و بررسی آن توسط پلتفرم.
 *
 * انتشار دو مرحله دارد: سالن اطلاعات را کامل و درخواست می‌کند، پلتفرم
 * بررسی می‌کند. سالنِ منتشرشده با ویرایش جزئی (متن معرفی، عکس) از فهرست
 * بیرون نمی‌رود — وگرنه هر اصلاح کوچک یعنی چند روز ناپیدا بودن.
 */
final class SalonPublicationController extends Controller
{
    private const MEDIA_DIR = '/public/uploads/media';

    public function edit(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$salonId]) ?? [];

        return $this->page('layouts.panel', 'panel.publication', [
            'title' => 'صفحهٔ عمومی سالن',
            'salon' => $salon,
            'checklist' => $this->checklist($salon),
        ]);
    }

    public function save(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$salonId]);
        if ($salon === null) {
            return $this->notFound();
        }

        $data = [
            'neighborhood' => $this->clean($request->input('neighborhood'), 100),
            'introduction' => $this->clean($request->input('introduction'), 1000),
        ];
        $errors = [];
        foreach (['map_lat' => 90, 'map_lng' => 180] as $field => $limit) {
            $raw = trim(str_replace(['٫', ','], '.', \App\Support\Jalali::fromPersianDigits((string) $request->input($field, ''))));
            if ($raw !== '' && (!is_numeric($raw) || abs((float) $raw) > $limit)) {
                $errors[$field] = 'عدد معتبر وارد کنید.';
            }
            $data[$field] = $raw === '' || isset($errors[$field]) ? null : round((float) $raw, 7);
        }
        if ($errors === [] && ($data['map_lat'] === null) !== ($data['map_lng'] === null)) {
            $errors['map_lng'] = 'عرض و طول جغرافیایی را با هم وارد کنید.';
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/panel/publication');
        }

        if ($request->input('remove_cover') === '1') {
            ImageUpload::delete(BASE_PATH . self::MEDIA_DIR, $salon['cover_path']);
            $data['cover_path'] = null;
        } else {
            $upload = ImageUpload::saveImage($request->file('cover'), BASE_PATH . self::MEDIA_DIR, 'salon-' . $salonId, 1600);
            if ($upload['error'] !== null) {
                return $this->invalid($request, ['cover' => $upload['error']], '/panel/publication');
            }
            if ($upload['ok']) {
                ImageUpload::delete(BASE_PATH . self::MEDIA_DIR, $salon['cover_path']);
                $data['cover_path'] = $upload['path'];
            }
        }

        $status = (string) $salon['publication_status'];
        $message = 'تغییرات ذخیره شد.';
        if ($request->input('request_publication') === '1' && in_array($status, ['draft', 'rejected'], true)) {
            $missing = array_keys(array_filter($this->checklist(array_merge($salon, $data)), static fn ($ok) => !$ok));
            if ($missing !== []) {
                DB::update('salons', $data, 'id = :id', ['id' => $salonId]);
                SalonRepository::forget($salonId);

                return $this->withError('تغییرات ذخیره شد، ولی برای درخواست انتشار این موارد کامل نیست: ' . implode('، ', $missing), '/panel/publication');
            }
            $data['publication_status'] = 'pending';
            $message = 'درخواست انتشار ثبت شد. پس از بررسی، سالن در فهرست «کشف» دیده می‌شود.';
        } elseif ($request->input('withdraw') === '1' && in_array($status, ['pending', 'published'], true)) {
            $data['publication_status'] = 'draft';
            $message = 'سالن از فهرست عمومی خارج شد. صفحهٔ رزرو با لینک مستقیم همچنان کار می‌کند.';
        }

        DB::update('salons', $data, 'id = :id', ['id' => $salonId]);
        SalonRepository::forget($salonId);

        return $this->withSuccess($message, '/panel/publication');
    }

    // ─── پلتفرم ───────────────────────────────────────────────────────

    public function moderation(Request $request): Response
    {
        $salons = DB::select(
            "SELECT s.*, (SELECT COUNT(*) FROM services sv WHERE sv.salon_id = s.id AND sv.is_active = 1) AS service_count,
                    (SELECT COUNT(*) FROM staff st WHERE st.salon_id = s.id AND st.is_active = 1) AS staff_count
               FROM salons s WHERE s.publication_status = 'pending' ORDER BY s.updated_at, s.id LIMIT 100"
        );
        $reviews = DB::select(
            "SELECT r.*, s.name AS salon_name, (SELECT COUNT(*) FROM review_reports rp WHERE rp.review_id = r.id) AS report_count
               FROM reviews r JOIN salons s ON s.id = r.salon_id
              WHERE r.moderation_status = 'pending'
                 OR (r.moderation_status = 'published' AND EXISTS (SELECT 1 FROM review_reports rp WHERE rp.review_id = r.id))
              ORDER BY r.id DESC LIMIT 100"
        );

        return $this->page('layouts.platform', 'platform.moderation', [
            'title' => 'بررسی انتشار و نظرها',
            'salons' => array_map(fn ($s) => $s + ['checks' => $this->checklist($s)], $salons),
            'reviews' => $reviews,
        ]);
    }

    public function moderate(Request $request): Response
    {
        $type = (string) $request->input('type');
        $id = (int) $request->input('id');
        $approve = $request->input('decision') === 'approve';
        if (!in_array($type, ['salon', 'review'], true) || $id <= 0) {
            return $this->withError('درخواست نامعتبر است.', '/platform/moderation');
        }

        if ($type === 'salon') {
            $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$id]);
            if ($salon === null) {
                return $this->withError('سالن یافت نشد.', '/platform/moderation');
            }
            if ($approve) {
                $missing = array_keys(array_filter($this->checklist($salon), static fn ($ok) => !$ok));
                if (!$salon['is_active'] || $missing !== []) {
                    return $this->withError('پیش از انتشار کامل شود: ' . ($missing !== [] ? implode('، ', $missing) : 'سالن غیرفعال است'), '/platform/moderation');
                }
            }
        }

        $review = $type === 'review' ? DB::selectOne('SELECT id, salon_id FROM reviews WHERE id = ?', [$id]) : null;
        if ($type === 'review' && $review === null) {
            return $this->withError('نظر یافت نشد.', '/platform/moderation');
        }

        DB::transaction(function () use ($type, $id, $approve, $review) {
            if ($type === 'salon') {
                DB::update('salons', ['publication_status' => $approve ? 'published' : 'rejected'], 'id = :id', ['id' => $id]);
                SalonRepository::forget($id);
            } else {
                DB::update('reviews', ['moderation_status' => $approve ? 'published' : 'hidden'], 'id = :id', ['id' => $id]);
                if ($approve) {
                    DB::delete('review_reports', 'review_id = ?', [$id]);
                }
                (new SalonStats())->refreshRating((int) $review['salon_id']);
            }
            DB::insert('audit_logs', [
                'salon_id' => $type === 'salon' ? $id : (int) $review['salon_id'],
                'actor_user_id' => Auth::id(),
                'action' => $approve ? 'publication_approve' : 'publication_reject',
                'subject_type' => $type,
                'subject_id' => $id,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        });

        return $this->withSuccess($approve ? 'تأیید و منتشر شد.' : ($type === 'salon' ? 'درخواست رد شد.' : 'نظر پنهان شد.'), '/platform/moderation');
    }

    /**
     * پیش‌نیازهای انتشار. کلید، متن خوانا برای پیام است.
     *
     * @return array<string,bool>
     */
    private function checklist(array $salon): array
    {
        $id = (int) ($salon['id'] ?? 0);

        return [
            'شهر' => trim((string) ($salon['city'] ?? '')) !== '',
            'نشانی' => trim((string) ($salon['address'] ?? '')) !== '',
            'تلفن' => trim((string) ($salon['phone'] ?? '')) !== '',
            'متن معرفی' => mb_strlen(trim((string) ($salon['introduction'] ?? ''))) >= 20,
            'خدمت فعال با قیمت' => $id > 0 && DB::selectOne('SELECT id FROM services WHERE salon_id = ? AND is_active = 1 AND price > 0 LIMIT 1', [$id]) !== null,
            term('staff', $salon['audience'] ?? null) . ' فعال' => $id > 0 && DB::selectOne('SELECT id FROM staff WHERE salon_id = ? AND is_active = 1 LIMIT 1', [$id]) !== null,
        ];
    }

    private function clean(mixed $value, int $max): ?string
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', (string) $value) ?? '');

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
