<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Customer\CustomerPreferencesRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\IranMobile;
use App\Support\SalonContext;

/**
 * پروندهٔ مشتری‌ها — آنچه آرایشگر سال‌ها در ذهنش نگه می‌داشت، اینجا
 * نوشته می‌شود تا با عوض شدن نیرو از بین نرود.
 */
final class SalonCustomersController extends Controller
{
    /** فیلدهای پرونده به تفکیک مخاطب سالن: [ستون، برچسب، راهنما] */
    public const PREFERENCE_FIELDS = [
        'men' => [
            ['clipper_size', 'شمارهٔ ماشین', 'مثلاً شمارهٔ ۲ کناره‌ها'],
            ['hair_shape', 'مدل مو', 'مثلاً فید کوتاه، فرق کنار'],
            ['beard_notes', 'ریش', 'فرم، طول، خط گونه'],
            ['skin_sensitivity', 'حساسیت پوستی', 'به تیغ، افترشیو یا محصول خاص'],
        ],
        'women' => [
            ['hair_type', 'نوع مو', 'مثلاً فر، نازک، دکلره‌شده'],
            ['color_formula', 'فرمول رنگ', 'برند، شماره و نسبت اکسیدان'],
            ['nail_notes', 'ناخن', 'فرم، طول، رنگ‌های محبوب'],
            ['skin_type', 'نوع پوست', 'خشک، چرب، حساس'],
            ['allergies', 'حساسیت و آلرژی', 'به محصول، چسب مژه، موم'],
        ],
    ];

    public function index(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $term = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $result = (new CustomerRepository())->page($salonId, $term, $page);

        return $this->page('layouts.panel', 'panel.customers.index', [
            'title' => 'مشتریان',
            'customers' => $result['rows'],
            'hasNext' => $result['hasNext'],
            'page' => $page,
            'q' => $term,
            'total' => (int) (DB::selectOne('SELECT COUNT(*) AS c FROM customers WHERE salon_id = ? AND deleted_at IS NULL', [$salonId])['c'] ?? 0),
        ]);
    }

    public function show(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $repo = new CustomerRepository();
        $customer = $repo->find($salonId, $id);
        if ($customer === null) {
            return $this->withError('مشتری یافت نشد.', '/panel/customers');
        }

        return $this->page('layouts.panel', 'panel.customers.show', [
            'title' => $customer['name'] ?: 'مشتری',
            'customer' => $customer,
            'preferences' => (new CustomerPreferencesRepository())->find($salonId, $id) ?? [],
            'fields' => self::fieldsFor(SalonContext::audience()),
            'history' => $repo->history($salonId, $id),
            'staff' => (new StaffRepository())->all($salonId, true),
            'spent' => (int) (DB::selectOne(
                "SELECT COALESCE(SUM(p.amount), 0) AS t FROM payments p JOIN appointments a ON a.id = p.appointment_id
                  WHERE a.salon_id = ? AND a.customer_id = ?",
                [$salonId, $id]
            )['t'] ?? 0),
        ]);
    }

    public function update(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $customer = (new CustomerRepository())->find($salonId, $id);
        if ($customer === null) {
            return $this->withError('مشتری یافت نشد.', '/panel/customers');
        }

        $phoneRaw = trim((string) $request->input('phone', ''));
        $phone = $phoneRaw !== '' ? IranMobile::tryParse($phoneRaw) : null;
        if ($phoneRaw !== '' && $phone === null) {
            // شمارهٔ نامعتبر قبلاً بی‌صدا شمارهٔ درست را پاک می‌کرد
            return $this->invalid($request, ['phone' => 'شمارهٔ موبایل معتبر نیست؛ تغییری ذخیره نشد.'], '/panel/customers/' . $id);
        }
        if ($phone !== null && $phone->e164 !== $customer['phone']
            && DB::selectOne('SELECT id FROM customers WHERE salon_id = ? AND phone = ? AND id <> ?', [$salonId, $phone->e164, $id])) {
            return $this->invalid($request, ['phone' => 'این شماره برای مشتری دیگری از همین سالن ثبت شده است.'], '/panel/customers/' . $id);
        }

        $preferred = (string) $request->input('preferred_staff_id', '');
        $preferredId = $preferred !== '' && DB::selectOne('SELECT id FROM staff WHERE id = ? AND salon_id = ?', [(int) $preferred, $salonId]) ? (int) $preferred : null;

        DB::update('customers', [
            'name' => mb_substr(trim((string) $request->input('name', '')), 0, 120) ?: null,
            'phone' => $phone?->e164,
            'preferred_staff_id' => $preferredId,
            'notes' => mb_substr(trim((string) $request->input('notes', '')), 0, 2000) ?: null,
        ], 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);

        $prefs = [];
        foreach (self::fieldsFor(SalonContext::audience()) as [$column]) {
            $prefs[$column] = mb_substr(trim((string) $request->input($column, '')), 0, 255) ?: null;
        }
        $prefs['last_barber_said'] = mb_substr(trim((string) $request->input('last_barber_said', '')), 0, 2000) ?: null;
        (new CustomerPreferencesRepository())->upsert($salonId, $id, $prefs);

        return $this->withSuccess('پروندهٔ مشتری ذخیره شد.', '/panel/customers/' . $id);
    }

    /** @return array<int,array{0:string,1:string,2:string}> */
    public static function fieldsFor(string $audience): array
    {
        return match ($audience) {
            'women' => self::PREFERENCE_FIELDS['women'],
            'unisex' => array_merge(self::PREFERENCE_FIELDS['women'], [self::PREFERENCE_FIELDS['men'][0], self::PREFERENCE_FIELDS['men'][2]]),
            default => self::PREFERENCE_FIELDS['men'],
        };
    }
}
