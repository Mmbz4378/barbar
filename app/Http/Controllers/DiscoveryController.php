<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Customer\CustomerAuth;
use App\Domain\Salon\DiscoveryRepository;
use App\Domain\Salon\SalonStats;
use App\Domain\Salon\WorkingHoursRepository;
use App\Support\SalonContext;

final class DiscoveryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [];
        foreach (['q', 'city', 'neighborhood', 'max_price', 'rating', 'page', 'audience', 'cat'] as $key) {
            $value = $request->query($key, '');
            $filters[$key] = is_string($value) ? trim($value) : '';
        }
        $filters['favorites'] = $request->path === '/me/favorites';
        if ($filters['favorites'] && !CustomerAuth::check()) {
            return $this->redirect('/me/login');
        }

        $repo = new DiscoveryRepository();
        $result = $repo->search($filters, CustomerAuth::phone());

        return $this->page('layouts.discover', 'discover.index', [
            'title' => $filters['favorites'] ? 'سالن‌های ذخیره‌شده' : 'کشف آرایشگاه و سالن زیبایی',
            'salons' => $result['rows'],
            'hasNext' => $result['hasNext'],
            'filters' => $filters,
            'cities' => $repo->cities(),
        ]);
    }

    public function show(Request $request): Response
    {
        $salon = (new DiscoveryRepository())->find((string) $request->param('slug'));
        if (!$salon) {
            return $this->notFound('این سالن در فهرست عمومی نیست. ممکن است هنوز منتشر نشده باشد.');
        }
        SalonContext::set($salon);
        $id = (int) $salon['id'];
        $customer = CustomerAuth::check();

        // کارکنان و نظرهای منتشرشده با نسخهٔ سالن کش می‌شوند: تغییر کارکنان و
        // انتشار نظر (refreshRating) هر دو نسخه را بالا می‌برند. نظر تازه با
        // وضعیت «در انتظار» ثبت می‌شود و تا بررسی، عمومی نیست.
        $ver = Cache::version("salon:$id");

        return $this->page('layouts.discover', 'discover.show', [
            'title' => $salon['name'],
            'description' => $salon['introduction'] ?? null,
            'salon' => $salon,
            'groups' => (new ServiceRepository())->grouped($id, true),
            'staff' => Cache::remember("salon:$id:v$ver:public-staff", 300, static fn () => DB::select('SELECT id, name, title, color FROM staff WHERE salon_id = ? AND is_active = 1 ORDER BY sort_order, id', [$id])),
            'hours' => (new WorkingHoursRepository())->salonDefaults($id),
            'reviews' => Cache::remember("salon:$id:v$ver:reviews", 300, static fn () => DB::select(
                "SELECT r.id, r.rating, r.comment, r.created_at, c.name AS customer_name
                   FROM reviews r LEFT JOIN customers c ON c.id = r.customer_id
                  WHERE r.salon_id = ? AND r.moderation_status = 'published' ORDER BY r.id DESC LIMIT 30",
                [$id]
            )),
            // مهمان فرم نمی‌گیرد (پیوند ورود می‌گیرد): بدون توکن CSRF نشستی ساخته
            // نمی‌شود و صفحهٔ عمومی سالن برای مهمان سبک و قابل کش می‌ماند.
            'customer' => $customer,
            'favorite' => $customer && DB::selectOne('SELECT salon_id FROM salon_favorites WHERE phone = ? AND salon_id = ?', [CustomerAuth::phone(), $id]) !== null,
        ]);
    }

    public function favorite(Request $request): Response
    {
        if (!CustomerAuth::check()) {
            return $this->withError('برای ذخیرهٔ سالن، اول با شمارهٔ موبایلت وارد شو.', '/me/login');
        }
        $salon = (new DiscoveryRepository())->find((string) $request->param('slug'));
        if (!$salon) {
            return $this->notFound();
        }
        if ($request->input('saved') === '1') {
            DB::statement('INSERT IGNORE INTO salon_favorites (phone, salon_id) VALUES (?, ?)', [CustomerAuth::phone(), $salon['id']]);
            $message = 'سالن ذخیره شد.';
        } else {
            DB::delete('salon_favorites', 'phone = ? AND salon_id = ?', [CustomerAuth::phone(), $salon['id']]);
            $message = 'سالن از فهرست ذخیره‌شده‌ها حذف شد.';
        }

        return $this->withSuccess($message, '/salons/view/' . $salon['slug']);
    }

    public function review(Request $request): Response
    {
        if (!CustomerAuth::check()) {
            return $this->redirect('/me/login');
        }
        $rating = filter_var($request->input('rating'), FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            return $this->withError('برای ثبت نظر، امتیاز ۱ تا ۵ را انتخاب کن.', '/me');
        }

        $saved = DB::transaction(function () use ($request, $rating) {
            $a = DB::selectOne(
                "SELECT a.* FROM appointments a JOIN customers c ON c.id = a.customer_id AND c.salon_id = a.salon_id
                  WHERE a.id = ? AND c.phone = ? AND a.status = 'completed' FOR UPDATE",
                [(int) $request->param('id'), CustomerAuth::phone()]
            );
            if (!$a || DB::selectOne('SELECT id FROM reviews WHERE appointment_id = ?', [$a['id']])) {
                return false;
            }
            DB::insert('reviews', [
                'salon_id' => $a['salon_id'],
                'appointment_id' => $a['id'],
                'customer_id' => $a['customer_id'],
                'rating' => $rating,
                'comment' => mb_substr(trim((string) $request->input('comment', '')), 0, 500),
                'moderation_status' => 'pending',
            ]);

            return true;
        });

        return $saved
            ? $this->withSuccess('ممنون! نظرت پس از بررسی منتشر می‌شود.', '/me')
            : $this->withError('برای هر مراجعهٔ انجام‌شده فقط یک نظر می‌توانی ثبت کنی.', '/me');
    }

    public function report(Request $request): Response
    {
        if (!CustomerAuth::check()) {
            return $this->redirect('/me/login');
        }
        $reason = trim((string) $request->input('reason', ''));
        $review = DB::selectOne(
            "SELECT r.id, s.slug FROM reviews r JOIN salons s ON s.id = r.salon_id
              WHERE r.id = ? AND r.moderation_status = 'published' AND " . DiscoveryRepository::VISIBLE,
            [(int) $request->param('id')]
        );
        if (!$review) {
            return $this->notFound();
        }
        if ($reason === '') {
            return $this->withError('دلیل گزارش را بنویس.', '/salons/view/' . $review['slug'] . '#reviews');
        }
        DB::statement('INSERT IGNORE INTO review_reports (review_id, phone, reason) VALUES (?, ?, ?)', [$review['id'], CustomerAuth::phone(), mb_substr($reason, 0, 300)]);

        return $this->withSuccess('گزارش برای بررسی ثبت شد.', '/salons/view/' . $review['slug']);
    }
}
