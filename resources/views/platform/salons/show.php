<?php
/**
 * @var array $salon
 * @var array $members
 * @var array $stats
 * @var array $auditLogs
 */
use App\Http\Controllers\PlatformSalonController as PSC;
use App\Support\Audience;
use App\Support\AuditLabels;

$active = (int) $salon['is_active'] === 1;
$memberMode = (string) old('member_mode', 'existing');
?>
<a class="back-link" href="<?= e(url('platform/salons')) ?>"><?= icon('chevron-start') ?> همهٔ سالن‌ها</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= e($salon['name']) ?> <?php if (!$active): ?><span class="badge badge--danger">غیرفعال</span><?php endif; ?></h1>
    <p class="page-head__sub"><?= e(Audience::options()[$salon['audience']] ?? '') ?> · <?= e($salon['city'] ?: 'شهر نامشخص') ?> · <span class="ltr">/s/<?= e($salon['slug']) ?></span></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('s/' . $salon['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> صفحهٔ رزرو</a>
    <a class="btn btn--secondary" href="<?= e(url('platform/salons/' . $salon['id'] . '/edit')) ?>"><?= icon('edit') ?> ویرایش</a>
    <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/impersonate')) ?>" data-confirm="وارد پنل این سالن می‌شوید (به‌عنوان صاحب). این ورود ثبت می‌شود." data-confirm-tone="neutral" data-confirm-ok="ورود به پنل">
      <?= csrf_field() ?><button class="btn btn--primary" type="submit"><?= icon('shield') ?> ورود به پنل سالن</button>
    </form>
  </div>
</div>

<div class="grid grid-main-aside" style="--gap:24px">
  <div class="stack stack-lg">
    <div class="stats" style="--cols:4">
      <div class="stat"><span class="stat__label">نوبت ثبت‌شده (۳۰ روز)</span><span class="stat__value num"><?= e(fa_num((int) ($stats['bookings'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">مراجعهٔ انجام‌شده</span><span class="stat__value num"><?= e(fa_num((int) ($stats['completed'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">فروش ثبت‌شده</span><span class="stat__value stat__value--sm num"><?= e(toman((int) ($stats['revenue'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">نیامده</span><span class="stat__value num"><?= e(fa_num((int) ($stats['no_show'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label"><?= e(term('staff_plural', $salon['audience'])) ?> فعال</span><span class="stat__value num"><?= e(fa_num((int) ($stats['staff'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">خدمات فعال</span><span class="stat__value num"><?= e(fa_num((int) ($stats['services'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">مشتری</span><span class="stat__value num"><?= e(fa_num((int) ($stats['customers'] ?? 0))) ?></span></div>
      <div class="stat"><span class="stat__label">پیامک فرستاده (۳۰ روز)</span><span class="stat__value num"><?= e(fa_num((int) ($stats['sms_sent'] ?? 0))) ?></span></div>
    </div>

    <section class="card" id="members" aria-labelledby="ps-members">
      <div class="card__header card__header--divided"><h2 class="card__title" id="ps-members">اعضای پنل</h2></div>
      <?php if ($members === []): ?>
        <div class="card__body"><p class="text-sm muted">هنوز کسی به پنل این سالن دسترسی ندارد.</p></div>
      <?php else: ?>
      <ul class="list" role="list">
        <?php foreach ($members as $m): $off = (int) $m['is_active'] !== 1 || (int) $m['user_active'] !== 1; ?>
          <li class="list-row<?= $off ? ' is-inactive' : '' ?>">
            <span class="avatar avatar--sm" aria-hidden="true"><?= e(initial($m['name'] ?: 'ک')) ?></span>
            <span class="list-row__body">
              <a class="list-row__title" href="<?= e(url('platform/users/' . $m['id'])) ?>"><?= e($m['name'] ?: 'بدون نام') ?></a>
              <span class="list-row__meta"><span class="ltr num"><?= e(phone_local((string) $m['phone'])) ?></span><?= $m['username'] ? ' · <span class="ltr">' . e($m['username']) . '</span>' : '' ?> · <?= (int) $m['has_password'] ? 'رمز دارد' : 'فقط کد پیامکی' ?><?= $m['last_login_at'] ? ' · آخرین ورود ' . e(jdate($m['last_login_at'], 'Y/m/d')) : '' ?></span>
            </span>
            <span class="list-row__end">
              <?php if ((int) $m['user_active'] !== 1): ?><span class="badge badge--danger">حساب مسدود</span><?php elseif ((int) $m['is_active'] !== 1): ?><span class="badge">دسترسی برداشته</span><?php endif; ?>
              <details class="more-menu">
                <summary class="btn btn--secondary btn--sm"><?= e(PSC::ROLES[$m['role']] ?? $m['role']) ?> <?= icon('chevron-down') ?></summary>
                <div class="more-menu__panel more-menu__panel--wide">
                  <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/members/' . $m['id'])) ?>" class="more-menu__form stack stack-sm">
                    <?= csrf_field() ?><input type="hidden" name="action" value="role">
                    <label class="field__label" for="role-<?= (int) $m['id'] ?>">نقش</label>
                    <select class="select" id="role-<?= (int) $m['id'] ?>" name="role">
                      <?php foreach (PSC::ROLES as $rk => $rl): ?><option value="<?= e($rk) ?>" <?= $m['role'] === $rk ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn--primary btn--sm" type="submit">ذخیرهٔ نقش</button>
                  </form>
                  <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/members/' . $m['id'])) ?>" <?= (int) $m['is_active'] === 1 ? 'data-confirm="دسترسی این فرد به پنل سالن برداشته شود؟"' : '' ?>>
                    <?= csrf_field() ?><input type="hidden" name="action" value="<?= (int) $m['is_active'] === 1 ? 'remove' : 'restore' ?>">
                    <button class="btn <?= (int) $m['is_active'] === 1 ? 'btn--danger-ghost' : 'btn--ghost' ?>" type="submit"><?= (int) $m['is_active'] === 1 ? icon('user-x') . ' برداشتن دسترسی' : icon('user-check') . ' بازگرداندن دسترسی' ?></button>
                  </form>
                  <a class="btn btn--ghost" href="<?= e(url('platform/users/' . $m['id'])) ?>"><?= icon('user') ?> حساب کاربر (رمز، مسدودی)</a>
                </div>
              </details>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="card__body">
        <details class="disclosure" <?= field_error('member_identifier') || field_error('member_name') || field_error('member_phone') || field_error('member_password') || field_error('member_username') ? 'open' : '' ?>>
          <summary><?= icon('user-plus') ?> افزودن عضو</summary>
          <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/members')) ?>" class="stack mt-3">
            <?= csrf_field() ?>
            <div class="grid-auto" style="--min:200px">
              <fieldset class="field">
                <legend class="field__label mb-2">حساب</legend>
                <div class="segmented">
                  <label><input type="radio" name="member_mode" value="existing" <?= $memberMode !== 'new' ? 'checked' : '' ?>><span>کاربر موجود</span></label>
                  <label><input type="radio" name="member_mode" value="new" <?= $memberMode === 'new' ? 'checked' : '' ?>><span>حساب تازه</span></label>
                </div>
              </fieldset>
              <div class="field">
                <label class="field__label" for="member-role">نقش</label>
                <select class="select" id="member-role" name="role">
                  <?php foreach (PSC::ROLES as $rk => $rl): ?><option value="<?= e($rk) ?>" <?= old('role', 'reception') === $rk ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="field">
              <label class="field__label" for="member_identifier">کاربر موجود: موبایل یا نام کاربری</label>
              <input class="input input--ltr" id="member_identifier" name="member_identifier" dir="ltr" autocapitalize="none" spellcheck="false" value="<?= e((string) old('member_identifier')) ?>" <?= field_error('member_identifier') ? 'aria-invalid="true" aria-describedby="member_identifier-error"' : '' ?>>
              <?= partial('field-error', ['key' => 'member_identifier']) ?>
            </div>
            <p class="text-sm muted">برای «حساب تازه»، این‌ها را پر کنید:</p>
            <?= App\Core\View::render('platform._account-fields', ['prefix' => 'member_', 'mustChangeDefault' => true]) ?>
            <div><button class="btn btn--primary" type="submit">افزودن به پنل سالن</button></div>
          </form>
        </details>
      </div>
    </section>

    <section class="card" aria-labelledby="ps-audit">
      <div class="card__header card__header--divided"><h2 class="card__title" id="ps-audit">رویدادهای این سالن</h2></div>
      <?php if ($auditLogs === []): ?>
        <div class="card__body"><p class="muted text-sm">رویدادی ثبت نشده.</p></div>
      <?php else: ?>
        <div class="table-wrap table-wrap--flush">
          <table class="table table--stack">
            <thead><tr><th scope="col">زمان</th><th scope="col">اقدام</th><th scope="col">انجام‌دهنده</th><th scope="col">IP</th></tr></thead>
            <tbody>
              <?php foreach ($auditLogs as $log): ?>
                <tr>
                  <td data-label="زمان" class="nowrap num"><?= e(jdate($log['created_at'], 'Y/m/d H:i')) ?></td>
                  <td data-label="اقدام"><?= e(AuditLabels::action((string) $log['action'])) ?></td>
                  <td data-label="انجام‌دهنده"><?= e($log['actor_name'] ?: phone_local($log['actor_phone'] ?? '') ?: 'سامانه') ?></td>
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
        <div class="kv__row"><dt>وضعیت</dt><dd><?= $active ? '<span class="badge badge--success">فعال</span>' : '<span class="badge badge--danger">غیرفعال</span>' ?></dd></div>
        <div class="kv__row"><dt>انتشار در کشف</dt><dd><?= e(PSC::PUBLICATION[$salon['publication_status']] ?? '—') ?></dd></div>
        <div class="kv__row"><dt>طرح</dt><dd><?= e(PSC::PLANS[$salon['plan_code']] ?? $salon['plan_code']) ?></dd></div>
        <?php if (!empty($salon['trial_ends_at']) && $salon['plan_code'] === 'trial'): ?><div class="kv__row"><dt>پایان آزمایشی</dt><dd class="num"><?= e(jdate($salon['trial_ends_at'], 'Y/m/d')) ?></dd></div><?php endif; ?>
        <div class="kv__row"><dt>تلفن</dt><dd class="ltr num"><?= e($salon['phone'] ?: '—') ?></dd></div>
        <div class="kv__row"><dt>نشانی</dt><dd><?= e($salon['address'] ?: '—') ?></dd></div>
        <div class="kv__row"><dt>صندلی</dt><dd class="num"><?= e(fa_num((int) $salon['seats'])) ?></dd></div>
        <div class="kv__row"><dt>روند رزرو</dt><dd><?= $salon['booking_flow'] === 'service_first' ? 'اول خدمت' : 'اول زمان' ?></dd></div>
        <div class="kv__row"><dt>تاریخ ثبت</dt><dd class="num"><?= e(jdate($salon['created_at'], 'Y/m/d')) ?></dd></div>
      </dl>
    </div></section>

    <section class="card" id="credit" aria-labelledby="ps-credit"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="ps-credit">اعتبار پیامک</h2>
      <p class="stat__value num <?= (int) $salon['sms_credit'] < 20 ? 'warning-text' : '' ?>"><?= e(fa_num((int) $salon['sms_credit'])) ?> <span class="text-sm muted">پیامک</span></p>
      <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/credit')) ?>" class="stack stack-sm">
        <?= csrf_field() ?>
        <div class="segmented segmented--block">
          <label><input type="radio" name="direction" value="credit" checked><span>شارژ</span></label>
          <label><input type="radio" name="direction" value="debit"><span>کسر</span></label>
        </div>
        <div class="field"><label class="field__label" for="credit-amount">تعداد</label><input class="input num" id="credit-amount" name="amount" inputmode="numeric" required data-numeric <?= field_error('amount') ? 'aria-invalid="true" aria-describedby="amount-error"' : '' ?>><?= partial('field-error', ['key' => 'amount']) ?></div>
        <div class="field"><label class="field__label" for="credit-reason">دلیل <span class="field__optional">(در رویدادها ثبت می‌شود)</span></label><input class="input" id="credit-reason" name="reason" maxlength="200" placeholder="مثلاً پرداخت فاکتور مهر"></div>
        <button class="btn btn--secondary" type="submit">ثبت</button>
      </form>
    </div></section>

    <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/active')) ?>" class="card" <?= $active ? 'data-confirm="سالن غیرفعال شود؟ صفحهٔ رزرو و پنل آن بسته می‌شود؛ داده‌ها حذف نمی‌شوند."' : '' ?>><div class="card__body stack stack-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
      <p class="text-sm muted"><?= $active ? 'برای تعلیق (تخلف یا عدم پرداخت). صفحهٔ رزرو و پنل بسته می‌شود؛ داده‌ها حذف نمی‌شوند.' : 'سالن غیرفعال است: مشتری‌ها رزرو نمی‌کنند و پنل بسته است.' ?></p>
      <button type="submit" class="btn <?= $active ? 'btn--danger-ghost' : 'btn--success' ?>"><?= $active ? 'غیرفعال کردن سالن' : 'فعال کردن سالن' ?></button>
    </div></form>
  </aside>
</div>
