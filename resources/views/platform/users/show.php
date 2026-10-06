<?php
/**
 * @var array $user
 * @var array $memberships
 * @var int   $bookings
 * @var array $logins
 * @var array $events
 * @var array $salons
 * @var bool  $isSelf
 * @var string|null $deny      اگر بیننده اجازهٔ تغییر این حساب را ندارد، دلیلش
 * @var bool  $viewerIsSuper
 * @var array|null $grantedBy
 */
use App\Domain\Identity\AdminPolicy;
use App\Domain\Identity\LoginEvents;
use App\Http\Controllers\PlatformSalonController as PSC;
use App\Support\AuditLabels;

$uid = (int) $user['id'];
$blocked = (int) $user['is_active'] !== 1;
$admin = (int) $user['is_platform_admin'] === 1;
$super = AdminPolicy::isSuper($user);
$locked = !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time();
$v = static fn (string $key) => old($key, (string) ($user[$key] ?? ''));
?>
<a class="back-link" href="<?= e(url('platform/users')) ?>"><?= icon('chevron-start') ?> همهٔ کاربران</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= e($user['name'] ?: 'بدون نام') ?>
      <?php if ($super): ?><span class="badge badge--accent">مدیر ارشد</span><?php elseif ($admin): ?><span class="badge badge--accent">مدیر کل</span><?php endif; ?>
      <?php if ($blocked): ?><span class="badge badge--danger">مسدود</span><?php elseif ($locked): ?><span class="badge badge--warning">قفل رمز</span><?php endif; ?>
      <?php if ($isSelf): ?><span class="badge">شما</span><?php endif; ?>
    </h1>
    <p class="page-head__sub"><span class="ltr num"><?= e(phone_local((string) $user['phone'])) ?></span><?= $user['username'] ? ' · <span class="ltr">' . e($user['username']) . '</span>' : '' ?> · عضو از <?= e(jdate((string) $user['created_at'], 'Y/m/d')) ?><?= $user['last_login_at'] ? ' · آخرین ورود ' . e(jdate((string) $user['last_login_at'], 'Y/m/d H:i')) : '' ?></p>
    <?php if ($admin): ?>
      <p class="page-head__sub"><?= !empty($user['admin_granted_at'])
          ? 'مدیر کل از ' . e(jdate((string) $user['admin_granted_at'], 'Y/m/d')) . ($grantedBy ? ' با اجازهٔ ' . e($grantedBy['name'] ?: phone_local((string) $grantedBy['phone'])) : ' (نصب یا راه‌اندازی)')
          : '<span class="badge badge--danger">هشدار</span> مدیریت کل از راه سامانه داده نشده (پرچم مستقیم در دیتابیس عوض شده).' ?></p>
    <?php endif; ?>
  </div>
</div>

<?php if ($deny !== null): ?>
  <div class="alert alert--info mb-6" role="note"><?= icon('lock') ?><div class="alert__body"><p class="alert__title"><?= e($deny) ?></p><p class="text-sm">مشخصات، رمز و دسترسی این حساب فقط نمایش داده می‌شود.</p></div></div>
<?php endif; ?>

<div class="grid grid-main-aside" style="--gap:24px">
  <div class="stack stack-lg">
    <section class="card" aria-labelledby="pu-profile"><div class="card__body stack">
      <h2 class="title-sm" id="pu-profile">مشخصات</h2>
      <form method="post" action="<?= e(url('platform/users/' . $uid)) ?>" class="stack">
        <?= csrf_field() ?>
        <fieldset class="stack" <?= $deny !== null ? 'disabled' : '' ?>>
        <div class="grid-auto" style="--min:220px">
          <div class="field"><label class="field__label" for="name">نام</label><input class="input" id="name" name="name" maxlength="120" value="<?= e((string) $v('name')) ?>"></div>
          <div class="field">
            <label class="field__label" for="phone">موبایل</label>
            <input class="input input--ltr num" id="phone" name="phone" type="tel" dir="ltr" required value="<?= e(phone_local((string) $v('phone'))) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
            <?= partial('field-error', ['key' => 'phone']) ?>
          </div>
          <div class="field">
            <label class="field__label" for="username">نام کاربری</label>
            <input class="input input--ltr" id="username" name="username" maxlength="40" dir="ltr" autocapitalize="none" spellcheck="false" value="<?= e((string) $v('username')) ?>" <?= field_error('username') ? 'aria-invalid="true" aria-describedby="username-error"' : '' ?>>
            <?= partial('field-error', ['key' => 'username']) ?>
          </div>
        </div>
        <?php if ($deny === null): ?><div><button class="btn btn--primary" type="submit">ذخیرهٔ مشخصات</button></div><?php endif; ?>
        </fieldset>
      </form>
    </div></section>

    <section class="card" id="memberships" aria-labelledby="pu-mem">
      <div class="card__header card__header--divided"><h2 class="card__title" id="pu-mem">دسترسی به سالن‌ها</h2></div>
      <?php if ($memberships === []): ?>
        <div class="card__body"><p class="text-sm muted"><?= $admin ? 'مدیر کل است و به همهٔ سالن‌ها از پنل مدیریت دسترسی دارد.' : 'به پنل هیچ سالنی دسترسی ندارد' . ($bookings > 0 ? '؛ مشتری است و ' . e(fa_num($bookings)) . ' نوبت رزرو کرده.' : '.') ?></p></div>
      <?php else: ?>
        <ul class="list" role="list">
          <?php foreach ($memberships as $m): ?>
            <li class="list-row<?= (int) $m['is_active'] !== 1 ? ' is-inactive' : '' ?>">
              <span class="icon-tile"><?= icon('store') ?></span>
              <span class="list-row__body"><a class="list-row__title" href="<?= e(url('platform/salons/' . $m['salon_id'])) ?>"><?= e($m['salon_name']) ?></a><span class="list-row__meta"><?= e(PSC::ROLES[$m['role']] ?? $m['role']) ?><?= (int) $m['is_active'] !== 1 ? ' · دسترسی برداشته' : '' ?><?= (int) $m['salon_active'] !== 1 ? ' · سالن غیرفعال' : '' ?></span></span>
              <?php if ($deny === null): ?><span class="list-row__end">
                <form method="post" action="<?= e(url('platform/salons/' . $m['salon_id'] . '/members/' . $uid)) ?>" <?= (int) $m['is_active'] === 1 ? 'data-confirm="دسترسی به این سالن برداشته شود؟"' : '' ?>>
                  <?= csrf_field() ?><input type="hidden" name="action" value="<?= (int) $m['is_active'] === 1 ? 'remove' : 'restore' ?>">
                  <button class="btn btn--sm <?= (int) $m['is_active'] === 1 ? 'btn--danger-ghost' : 'btn--ghost' ?>" type="submit"><?= (int) $m['is_active'] === 1 ? 'برداشتن' : 'بازگرداندن' ?></button>
                </form>
              </span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($deny === null): ?>
      <div class="card__body">
        <form method="post" action="<?= e(url('platform/users/' . $uid . '/memberships')) ?>" class="cluster items-end" style="--gap:12px">
          <?= csrf_field() ?>
          <div class="field grow-200"><label class="field__label" for="mem-salon">سالن</label><select class="select" id="mem-salon" name="salon_id"><?php foreach ($salons as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label class="field__label" for="mem-role">نقش</label><select class="select" id="mem-role" name="role"><?php foreach (PSC::ROLES as $rk => $rl): ?><option value="<?= e($rk) ?>" <?= $rk === 'reception' ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?></select></div>
          <button class="btn btn--secondary" type="submit"><?= icon('plus') ?> افزودن دسترسی</button>
        </form>
      </div>
      <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="pu-logins">
      <div class="card__header card__header--divided"><h2 class="card__title" id="pu-logins">ورودهای اخیر</h2></div>
      <?php if ($logins === []): ?>
        <div class="card__body"><p class="text-sm muted">ورودی ثبت نشده.</p></div>
      <?php else: ?>
        <div class="table-wrap table-wrap--flush">
          <table class="table table--stack">
            <thead><tr><th scope="col">زمان</th><th scope="col">روش</th><th scope="col">نتیجه</th><th scope="col">IP</th></tr></thead>
            <tbody>
              <?php foreach ($logins as $ev): ?>
                <tr>
                  <td data-label="زمان" class="num nowrap"><?= e(jdate((string) $ev['created_at'])) ?></td>
                  <td data-label="روش"><?= e(LoginEvents::methodLabel((string) $ev['method'])) ?></td>
                  <td data-label="نتیجه"><?php if ((int) $ev['success'] === 1): ?><span class="badge badge--success">موفق</span><?php else: ?><span class="badge badge--danger">ناموفق</span> <span class="text-sm muted"><?= e(LoginEvents::reasonLabel($ev['reason'] ?? null)) ?></span><?php endif; ?></td>
                  <td data-label="IP" class="ltr text-xs muted"><?= e((string) ($ev['ip_address'] ?? '—')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="pu-events">
      <div class="card__header card__header--divided"><h2 class="card__title" id="pu-events">رویدادها</h2></div>
      <?php if ($events === []): ?>
        <div class="card__body"><p class="text-sm muted">رویدادی ثبت نشده.</p></div>
      <?php else: ?>
        <div class="table-wrap table-wrap--flush">
          <table class="table table--stack">
            <thead><tr><th scope="col">زمان</th><th scope="col">اقدام</th><th scope="col">سالن</th><th scope="col">انجام‌دهنده</th></tr></thead>
            <tbody>
              <?php foreach ($events as $log): ?>
                <tr>
                  <td data-label="زمان" class="num nowrap"><?= e(jdate((string) $log['created_at'], 'Y/m/d H:i')) ?></td>
                  <td data-label="اقدام"><?= e(AuditLabels::action((string) $log['action'])) ?></td>
                  <td data-label="سالن"><?= e($log['salon_name'] ?? '—') ?></td>
                  <td data-label="انجام‌دهنده"><?= e($log['actor_name'] ?? 'سامانه') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="card" id="password" aria-labelledby="pu-pass"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="pu-pass">رمز</h2>
      <p class="text-sm muted"><?= !empty($user['password_hash']) ? 'رمز دارد' . ($user['password_changed_at'] ? ' (تغییر: ' . e(jdate((string) $user['password_changed_at'], 'Y/m/d')) . ')' : '') . '.' : 'هنوز رمز ندارد؛ با کد پیامکی وارد می‌شود.' ?><?= (int) $user['must_change_password'] ? ' رمز فعلی موقت است.' : '' ?></p>
      <?php if ($deny === null): ?>
      <form method="post" action="<?= e(url('platform/users/' . $uid . '/password')) ?>" class="stack stack-sm" data-confirm="رمز تازه گذاشته شود؟ نشست‌های باز این کاربر بسته می‌شود." data-confirm-tone="neutral" data-confirm-ok="ذخیرهٔ رمز">
        <?= csrf_field() ?>
        <div class="field">
          <label class="field__label" for="new-pass">رمز تازه</label>
          <input class="input input--ltr" id="new-pass" name="password" type="password" dir="ltr" autocomplete="new-password" <?= field_error('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'password']) ?>
        </div>
        <label class="check check--compact"><input type="checkbox" name="generate" value="1"><span>رمز تصادفی بساز</span></label>
        <?php if (!$isSelf): ?><label class="check check--compact"><input type="checkbox" name="must_change" value="1" checked><span>در ورود بعدی رمزش را عوض کند</span></label><?php endif; ?>
        <button class="btn btn--secondary" type="submit"><?= icon('lock') ?> تعیین رمز</button>
      </form>
      <?php endif; ?>
    </div></section>

    <section class="card" aria-labelledby="pu-sec"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="pu-sec">دسترسی و امنیت</h2>
      <?php $act = static function (string $action, string $label, string $class, ?string $confirm = null) use ($uid): string {
          return '<form method="post" action="' . e(url('platform/users/' . $uid . '/status')) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
              . '<input type="hidden" name="action" value="' . e($action) . '"><button class="btn btn--block ' . e($class) . '" type="submit">' . e($label) . '</button></form>';
      }; ?>
      <?php if ($deny !== null): ?>
        <p class="text-sm muted"><?= e($deny) ?></p>
      <?php else: ?>
        <?php if ($locked): ?><?= $act('unlock', 'بازکردن قفل ورود با رمز', 'btn--secondary') ?><?php endif; ?>
        <?= $act('logout', 'بستن همهٔ نشست‌ها', 'btn--ghost', 'همهٔ نشست‌های باز این کاربر بسته شود؟') ?>
        <?php if (!$isSelf): ?>
          <?php if ($viewerIsSuper): ?>
            <?= $admin ? $act('revoke_admin', 'گرفتن مدیریت کل', 'btn--danger-ghost', 'مدیریت کل از این کاربر گرفته شود؟ نشست‌های بازش همین حالا بسته می‌شوند.') : (!$blocked ? $act('grant_admin', 'مدیر کل کردن', 'btn--ghost', 'این کاربر به پنل مدیریت دسترسی کامل پیدا می‌کند (جز کارهای مدیر ارشد). ادامه می‌دهید؟') : '') ?>
          <?php elseif (!$admin): ?>
            <p class="text-xs muted">مدیر کل کردن فقط از مدیر ارشد ساخته است.</p>
          <?php endif; ?>
          <?= $blocked ? $act('unblock', 'رفع مسدودی', 'btn--success') : $act('block', 'مسدود کردن حساب', 'btn--danger-ghost', 'حساب مسدود شود؟ نشست‌های باز همین حالا بسته می‌شوند و دیگر نمی‌تواند وارد شود.') ?>
        <?php else: ?>
          <p class="text-xs muted">مسدودکردن و گرفتن مدیریت کل برای حساب خودتان ممکن نیست.<?= $super ? ' مدیر ارشد هستید: فقط شما مدیر کل می‌سازید یا برمی‌دارید.' : '' ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </div></section>

    <?php if ($viewerIsSuper && !$isSelf && $admin && !$blocked): ?>
      <section class="card" id="super" aria-labelledby="pu-super"><div class="card__body stack stack-sm">
        <h2 class="card__title" id="pu-super">سپردن مدیر ارشدی</h2>
        <p class="text-sm muted">این کاربر مدیر ارشد می‌شود و شما مدیر کل می‌مانید. پس از آن ساختن و برداشتن مدیرها و تنظیمات حساس فقط از او ساخته است.</p>
        <form method="post" action="<?= e(url('platform/users/' . $uid . '/status')) ?>" class="stack stack-sm" data-confirm="مدیر ارشدی به این کاربر سپرده شود؟ برگرداندنش فقط از دست او ساخته است.">
          <?= csrf_field() ?><input type="hidden" name="action" value="transfer_super">
          <?php if (!empty((App\Core\Auth::user() ?? [])['password_hash'])): ?>
            <div class="field"><label class="field__label" for="super-pass">رمز فعلی خودتان</label><input class="input input--ltr" id="super-pass" name="current_password" type="password" dir="ltr" required autocomplete="current-password"></div>
          <?php endif; ?>
          <button class="btn btn--danger-ghost btn--block" type="submit"><?= icon('shield') ?> سپردن مدیر ارشدی</button>
        </form>
      </div></section>
    <?php endif; ?>
  </aside>
</div>
