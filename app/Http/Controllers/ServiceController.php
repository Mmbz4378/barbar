<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Catalog\CategoryRepository;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\ImageUpload;
use App\Support\SalonContext;
use App\Support\ServiceVisual;
use RuntimeException;

/**
 * خدمات، دسته‌ها و «چه کسی چه خدمتی را با چه قیمتی انجام می‌دهد».
 */
final class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $repo = new ServiceRepository();

        $offerCounts = [];
        $staffCount = (new StaffRepository())->countActive($salonId);
        foreach ($repo->all($salonId) as $service) {
            $offerCounts[(int) $service['id']] = count($repo->staffFor($salonId, (int) $service['id']));
        }

        return $this->page('layouts.panel', 'panel.services.index', [
            'title' => 'خدمات و قیمت‌ها',
            'groups' => $repo->grouped($salonId),
            'categories' => (new CategoryRepository())->all($salonId),
            'offerCounts' => $offerCounts,
            'staffCount' => $staffCount,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, (int) ($request->query('category') ?? 0) ?: null);
    }

    public function edit(Request $request): Response
    {
        $service = (new ServiceRepository())->find((int) Auth::salonId(), (int) $request->param('id'));
        if ($service === null) {
            return $this->withError('خدمت یافت نشد.', '/panel/services');
        }

        return $this->form($service, null);
    }

    private function form(?array $service, ?int $categoryId): Response
    {
        $salonId = (int) Auth::salonId();
        $overrides = [];
        if ($service !== null) {
            foreach ((new ServiceRepository())->overridesFor($salonId, (int) $service['id']) as $row) {
                $overrides[(int) $row['staff_id']] = $row;
            }
        }

        return $this->page('layouts.panel', 'panel.services.form', [
            'title' => $service ? 'ویرایش خدمت' : 'خدمت جدید',
            'service' => $service,
            'categoryId' => $service['category_id'] ?? $categoryId,
            'categories' => (new CategoryRepository())->all($salonId),
            'staff' => (new StaffRepository())->all($salonId, true),
            'overrides' => $overrides,
        ]);
    }

    public function store(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        [$data, $errors] = $this->validated($request, $salonId);
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/panel/services/create');
        }

        $id = (new ServiceRepository())->create($salonId, $data);
        $this->saveStaff($request, $salonId, $id);

        return $this->withSuccess('خدمت اضافه شد.' . ((int) $data['is_active'] === 0 ? ' چون قیمت ندارد، غیرفعال ماند.' : ''), '/panel/services/' . $id . '/edit');
    }

    public function update(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $repo = new ServiceRepository();
        $current = $repo->find($salonId, $id);
        if ($current === null) {
            return $this->notFound('خدمت یافت نشد.');
        }

        [$data, $errors] = $this->validated($request, $salonId, $current);
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/panel/services/' . $id . '/edit');
        }
        unset($data['is_active']);
        $repo->update($salonId, $id, $data);
        $this->saveStaff($request, $salonId, $id);

        return $this->withSuccess('تغییرات ذخیره شد.', '/panel/services/' . $id . '/edit');
    }

    public function toggle(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $repo = new ServiceRepository();
        $service = $repo->find($salonId, $id);
        if ($service !== null) {
            if (!(bool) $service['is_active'] && (int) $service['price'] <= 0 && $service['price_type'] !== 'from') {
                return $this->withError('برای فعال کردن «' . $service['name'] . '» اول قیمتش را وارد کنید.', '/panel/services/' . $id . '/edit');
            }
            $repo->setActive($salonId, $id, !(bool) $service['is_active']);
        }

        return $this->back('/panel/services');
    }

    /** آپلود عکس واقعی خدمت. */
    public function image(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $service = (new ServiceRepository())->find($salonId, $id);
        if ($service === null) {
            return $this->notFound('خدمت یافت نشد.');
        }

        if ($request->input('remove') === '1') {
            ImageUpload::delete(BASE_PATH . '/public/uploads/media', $service['image_file']);
            (new ServiceRepository())->update($salonId, $id, ['image_file' => null]);

            return $this->withSuccess('عکس حذف شد.', '/panel/services/' . $id . '/edit');
        }

        $upload = ImageUpload::saveImage($request->file('image'), BASE_PATH . '/public/uploads/media', 'service-' . $id, 960);
        if (!$upload['ok']) {
            return $this->withError($upload['error'] ?? 'یک عکس انتخاب کنید.', '/panel/services/' . $id . '/edit');
        }
        ImageUpload::delete(BASE_PATH . '/public/uploads/media', $service['image_file']);
        (new ServiceRepository())->update($salonId, $id, ['image_file' => $upload['path']]);

        return $this->withSuccess('عکس خدمت ذخیره شد.', '/panel/services/' . $id . '/edit');
    }

    // ─── دسته‌ها ───────────────────────────────────────────────────────

    public function storeCategory(Request $request): Response
    {
        try {
            (new CategoryRepository())->create((int) Auth::salonId(), (string) $request->input('name', ''), (string) $request->input('visual', 'haircut'));
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel/services');
        }

        return $this->withSuccess('دسته اضافه شد.', '/panel/services');
    }

    public function updateCategory(Request $request): Response
    {
        $repo = new CategoryRepository();
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        if ($repo->find($salonId, $id) === null) {
            return $this->notFound();
        }

        $action = (string) $request->input('action', 'save');
        try {
            match ($action) {
                'up' => $repo->move($salonId, $id, -1),
                'down' => $repo->move($salonId, $id, 1),
                'delete' => $repo->delete($salonId, $id),
                default => $repo->update($salonId, $id, (string) $request->input('name', ''), (string) $request->input('visual', 'haircut')),
            };
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel/services');
        }

        return $this->withSuccess($action === 'delete' ? 'دسته حذف شد؛ خدماتش در «سایر خدمات» ماند.' : 'دسته به‌روز شد.', '/panel/services');
    }

    /**
     * @return array{0:array,1:array<string,string>}
     */
    private function validated(Request $request, int $salonId, ?array $current = null): array
    {
        $errors = [];
        $name = mb_substr(trim((string) $request->input('name', '')), 0, 120);
        if ($name === '') {
            $errors['name'] = 'نام خدمت را وارد کنید.';
        }
        $duration = int_input($request->input('duration_minutes'));
        if ($duration === null || $duration < 5 || $duration > 720) {
            $errors['duration_minutes'] = 'مدت باید بین ۵ تا ۷۲۰ دقیقه باشد.';
        }
        $price = int_input($request->input('price_toman'));
        if ($price === null || $price < 0) {
            $errors['price_toman'] = 'قیمت را به تومان وارد کنید (صفر یعنی رایگان).';
        }
        $buffer = int_input($request->input('buffer_minutes')) ?? 0;
        $deposit = int_input($request->input('deposit_toman'));
        $categoryRaw = (string) $request->input('category_id', '');
        $categoryId = $categoryRaw !== '' ? (int) $categoryRaw : null;
        if (!(new CategoryRepository())->belongs($salonId, $categoryId)) {
            $errors['category_id'] = 'دسته معتبر نیست.';
        }
        $priceType = $request->input('price_type') === 'from' ? 'from' : 'fixed';
        $audience = (string) $request->input('audience', 'all');

        return [[
            'name' => $name,
            'category_id' => $categoryId,
            'description' => mb_substr(trim((string) $request->input('description', '')), 0, 300) ?: null,
            'duration_minutes' => max(5, min(720, (int) $duration)),
            'buffer_minutes' => max(0, min(120, $buffer)),
            'price' => max(0, (int) $price) * 10,
            'price_type' => $priceType,
            'deposit_amount' => $deposit !== null && $deposit > 0 ? $deposit * 10 : null,
            'online_booking' => $request->input('online_booking') === '1' ? 1 : 0,
            'audience' => SalonContext::audience() === 'unisex' && in_array($audience, ['all', 'men', 'women'], true) ? $audience : 'all',
            // بی‌قیمتِ «ثابت» غیرفعال می‌ماند تا با «رایگان» در صفحهٔ عمومی دیده نشود
            'is_active' => $current === null ? ((int) $price > 0 || $priceType === 'from' ? 1 : 0) : (int) $current['is_active'],
        ], $errors];
    }

    /** جدول «چه کسی انجام می‌دهد» و قیمت/مدت اختصاصی هر نفر. */
    private function saveStaff(Request $request, int $salonId, int $serviceId): void
    {
        $rows = $request->input('staff');
        if (!is_array($rows)) {
            return;
        }
        $repo = new ServiceRepository();
        foreach ((new StaffRepository())->all($salonId, true) as $member) {
            $row = $rows[(string) $member['id']] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $duration = int_input($row['duration'] ?? null);
            $price = int_input($row['price'] ?? null);
            $repo->setOverride(
                $salonId,
                (int) $member['id'],
                $serviceId,
                $duration !== null && $duration > 0 ? $duration : null,
                $price !== null && $price >= 0 && ($row['price'] ?? '') !== '' ? $price * 10 : null,
                ($row['offered'] ?? '') === '1',
            );
        }
    }
}
