<?php
/**
 * @var array $salon
 * @var array $members
 * @var array $stats
 * @var array $auditLogs
 */
use App\Support\Audience;

$plans = ['trial' => 'آزمایشی', 'basic' => 'پایه', 'pro' => 'حرفه‌ای', 'free' => 'رایگان'];

$pub = ['draft' => 'پیش‌نویس', 'pending' => 'در انتظار بررسی', 'published' => 'منتشرشده', 'rejected' => 'رد شده'][$salon['publication_status']] ?? '—';
$actions = [
    'support_login_as' => 'ورود پشتیبانی', 'salon_activate' => 'فعال‌سازی سالن', 'salon_deactivate' => 'غیرفعال‌سازی سالن',
    'publication_approve' => 'تأیید انتشار', 'publication_reject' => 'رد انتشار',
];
?>
<a class="back-link" href="<?= e(url('platform')) ?>"><?= icon('chevron-start') ?> همهٔ سالن‌ها</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= e($salon['name']) ?></h1>
    <p class="page-head__sub"><?= e(Audience::options()[$salon['audience']] ?? '') ?> · <?= e($salon['city'] ?: 'شهر نامشخص') ?> · <span class="ltr">/s/<?= e($salon['slug']) ?></span></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('s/' . $salon['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> صفحهٔ رزرو</a>
    <?php if ((int) $salon['is_active']): ?>
      <form method="post" action="<?= e(url('platform/' . $salon['id'] . '/impersonate')) ?>" data-confirm="وارد پنل این سالن می‌شوید. این ورود ثبت می‌شود." data-confirm-tone="neutral" data-confirm-ok="ورود به پنل">
        <?= csrf_field() ?><button class="btn btn--primary" type="submit"><?= icon('shield') ?> ورود پشتیبانی</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-main-aside" style="--gap:24px">
  <div class="stack stack-lg">
    <div class="stats" style="--cols:3">
      <div class="stat"><span class="stat__label">مراجعهٔ انجام‌شده (۳۰ روز)</span><span class="stat__value num"><?= e(fa_num((int) ($stats['completed'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">فروش ثبت‌شده (۳۰ روز)</span><span class="stat__value stat__value--sm num"><?= e(toman((int) ($stats['revenue'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">نیامده (۳۰ روز)</span><span class="stat__value num"><?= e(fa_num((int) ($stats['no_show'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label"><?= e(term('staff_plural', $salon['audience'])) ?> فعال</span><span class="stat__value num"><?= e(fa_num((int) ($stats['staff'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">خدمات فعال</span><span class="stat__value num"><?= e(fa_num((int) ($stats['services'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">مشتری</span><span class="stat__value num"><?= e(fa_num((int) ($stats['customers'] ?? 0))) ?></span></div>
    </div>

    <section class="card" aria-labelledby="ps-members">
      <div class="card__header card__header--divided"><h2 class="card__title" id="ps-members">اعضای پنل</h2></div>
      <ul class="list" role="list">
        <?php foreach ($members as $m): ?>
          <li class="list-row">
            <span class="avatar avatar--sm" aria-hidden="true"><?= e(initial($m['name'] ?: 'ک')) ?></span>
            <span class="list-row__body"><span class="list-row__title"><?= e($m['name'] ?: 'بدون نام') ?></span><span class="list-row__meta ltr num"><?= e(phone_local($m['phone'])) ?></span></span>
            <span class="list-row__end"><span class="badge"><?= e(role_label($m['role'])) ?></span></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card" aria-labelledby="ps-audit">
      <div class="card__header card__header--divided"><h2 class="card__title" id="ps-audit">رویدادهای ثبت‌شده</h2></div>
      <?php if ($auditLogs === []): ?>
        <div class="card__body"><p class="muted text-sm">رویدادی ثبت نشده.</p></div>
      <?php else: ?>
        <div class="table-wrap table-wrap--flush">
          <table class="table table--stack">
            <thead><tr><th scope="col">زمان</th><th scope="col">اقدام</th><th scope="col">انجام‌دهنده</th><th scope="col">IP</th></tr></thead>
            <tbody>
              <?php foreach ($auditLogs as $log): ?>
                <tr>
                  <td data-label="زمان" class="nowrap"><?= e(jdate($log['created_at'], 'Y/m/d H:i')) ?></td>
                  <td data-label="اقدام"><?= e($actions[$log['action']] ?? $log['action']) ?></td>
                  <td data-label="انجام‌دهنده"><?= e($log['actor_name'] ?: phone_local($log['actor_phone'] ?? '') ?: '—') ?></td>
                  <td data-label="IP" class="ltr text-xs muted"><?= e($log['ip_address'] ?? '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="card" aria-labelledby="ps-info"><div class="card__header"><h2 class="card__title" id="ps-info">مشخصات</h2></div><div class="card__body">
      <dl class="kv">
        <div class="kv__row"><dt>وضعیت</dt><dd><?= (int) $salon['is_active'] ? '<span class="badge badge--success">فعال</span>' : '<span class="badge badge--danger">غیرفعال</span>' ?></dd></div>
        <div class="kv__row"><dt>انتشار</dt><dd><?= e($pub) ?></dd></div>
        <div class="kv__row"><dt>طرح</dt><dd><?= e($plans[$salon['plan_code']] ?? $salon['plan_code']) ?></dd></div>
        <?php if (!empty($salon['trial_ends_at'])): ?><div class="kv__row"><dt>پایان دورهٔ آزمایشی</dt><dd><?= e(jdate($salon['trial_ends_at'], 'Y/m/d')) ?></dd></div><?php endif; ?>
        <div class="kv__row"><dt>اعتبار پیامک</dt><dd class="num"><?= e(fa_num((int) $salon['sms_credit'])) ?></dd></div>
        <div class="kv__row"><dt>تلفن</dt><dd class="ltr num"><?= e($salon['phone'] ?: '—') ?></dd></div>
        <div class="kv__row"><dt>نشانی</dt><dd><?= e($salon['address'] ?: '—') ?></dd></div>
        <div class="kv__row"><dt>روند رزرو</dt><dd><?= $salon['booking_flow'] === 'service_first' ? 'اول خدمت' : 'اول زمان' ?></dd></div>
        <div class="kv__row"><dt>تاریخ ثبت</dt><dd><?= e(jdate($salon['created_at'], 'Y/m/d')) ?></dd></div>
      </dl>
    </div></section>

    <form method="post" action="<?= e(url('platform/' . $salon['id'] . '/active')) ?>" class="card" <?= (int) $salon['is_active'] ? 'data-confirm="سالن غیرفعال شود؟ صفحهٔ رزرو و پنل آن از دسترس خارج می‌شود."' : '' ?>><div class="card__body stack stack-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="active" value="<?= (int) $salon['is_active'] ? '0' : '1' ?>">
      <p class="text-sm muted"><?= (int) $salon['is_active'] ? 'برای تخلف یا عدم پرداخت. داده‌ها حذف نمی‌شوند.' : 'سالن غیرفعال است و مشتری‌ها نمی‌توانند رزرو کنند.' ?></p>
      <button type="submit" class="btn <?= (int) $salon['is_active'] ? 'btn--danger-ghost' : 'btn--success' ?>"><?= (int) $salon['is_active'] ? 'غیرفعال کردن سالن' : 'فعال کردن سالن' ?></button>
    </div></form>
  </aside>
</div>
