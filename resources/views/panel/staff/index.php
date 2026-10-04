<?php
/**
 * @var array $staff
 * @var array $accessOnly
 * @var bool $isOwner
 * @var int $serviceCount
 */
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">تیم و دسترسی‌ها</h1>
    <p class="page-head__sub"><?= e(term('staff_plural')) ?> در رزرو دیده می‌شوند؛ هر کس شمارهٔ موبایل داشته باشد می‌تواند با نقشی که تعیین می‌کنید وارد پنل شود.</p>
  </div>
  <div class="page-head__actions">
    <button type="button" class="btn btn--secondary" data-open="member-sheet"><?= icon('user-plus') ?> دسترسی پذیرش/مدیر</button>
    <a class="btn btn--primary" href="<?= e(url('panel/staff/create')) ?>"><?= icon('plus') ?> افزودن <?= e(term('staff')) ?></a>
  </div>
</div>

<section class="section" aria-labelledby="providers-title">
  <h2 class="section__title" id="providers-title"><?= e(term('staff_plural')) ?></h2>
  <?php if ($staff === []): ?>
    <div class="card"><?= partial('empty-state', ['icon' => 'users', 'title' => 'هنوز کسی اضافه نشده', 'text' => 'دست‌کم یک ' . term('staff') . ' فعال لازم است تا مشتری بتواند نوبت بگیرد.', 'actionHref' => url('panel/staff/create'), 'actionLabel' => 'افزودن اولین نفر']) ?></div>
  <?php else: ?>
    <ul class="card list">
      <?php foreach ($staff as $st): $active = (bool) $st['is_active']; ?>
        <li class="list-row" style="<?= $active ? '' : 'opacity:.65' ?>">
          <span class="avatar" style="--avatar-bg:<?= e(staff_color($st['color'])) ?>" aria-hidden="true"><?= e(initial($st['name'])) ?></span>
          <a class="list-row__body" href="<?= e(url('panel/staff/' . $st['id'] . '/edit')) ?>" style="color:inherit;text-decoration:none">
            <span class="list-row__title"><?= e($st['name']) ?><?= $st['title'] ? ' <span class="muted text-sm">· ' . e($st['title']) . '</span>' : '' ?></span>
            <span class="cluster" style="--gap:4px">
              <?php if (!$active): ?><span class="badge badge--warning">غیرفعال</span><?php endif; ?>
              <?php if ($st['account_role']): ?>
                <span class="badge <?= (int) $st['account_active'] === 1 ? 'badge--accent' : '' ?>"><?= icon('lock') ?> <?= e(role_label($st['account_role'])) ?><?= (int) $st['account_active'] === 1 ? '' : ' (بدون دسترسی)' ?></span>
              <?php else: ?>
                <span class="badge">بدون حساب پنل</span>
              <?php endif; ?>
              <?php if ((int) $st['accepts_online'] !== 1): ?><span class="badge">فقط حضوری</span><?php endif; ?>
              <?php if ((int) $st['future_count'] > 0): ?><span class="badge badge--info"><?= e(fa_num($st['future_count'])) ?> نوبت پیش رو</span><?php endif; ?>
            </span>
          </a>
          <span class="list-row__end">
            <form method="post" action="<?= e(url('panel/staff/' . $st['id'] . '/toggle')) ?>"
                  <?php if ($active): ?>data-confirm="<?= e($st['name'] . ' غیرفعال شود؟ دیگر در رزرو دیده نمی‌شود' . ((int) $st['future_count'] > 0 ? ' و ' . fa_num($st['future_count']) . ' نوبت پیش رویش باید جابه‌جا یا لغو شود.' : '.')) ?>" data-confirm-ok="غیرفعال کن"<?php endif; ?>>
              <?= csrf_field() ?><input type="hidden" name="force" value="1">
              <button type="submit" class="btn btn--ghost btn--sm"><?= $active ? 'غیرفعال کن' : 'فعال کن' ?></button>
            </form>
            <a class="btn btn--secondary btn--icon btn--sm" href="<?= e(url('panel/staff/' . $st['id'] . '/edit')) ?>" aria-label="ویرایش <?= e($st['name']) ?>"><?= icon('edit') ?></a>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="section" id="members" aria-labelledby="members-title">
  <div class="section__head">
    <h2 class="section__title" id="members-title">دسترسی به پنل</h2>
  </div>
  <p class="section__sub">کسانی که خدمت نمی‌دهند ولی به پنل نیاز دارند (پذیرش، مدیر). دسترسی <?= e(term('staff_plural')) ?> از پروفایل خودشان تنظیم می‌شود.</p>
  <div class="card">
    <ul class="list">
      <?php foreach ($accessOnly as $m): $isSelf = (int) $m['user_id'] === (int) App\Core\Auth::id(); $locked = $m['role'] === 'owner' || $isSelf || ($m['role'] === 'manager' && !$isOwner); ?>
        <li class="list-row" style="<?= (int) $m['is_active'] === 1 ? '' : 'opacity:.6' ?>">
          <span class="avatar avatar--sm avatar--any" aria-hidden="true"><?= e(initial($m['name'] ?: '؟')) ?></span>
          <span class="list-row__body">
            <span class="list-row__title"><?= e($m['name'] ?: 'بدون نام') ?><?= $isSelf ? ' <span class="muted text-sm">(شما)</span>' : '' ?></span>
            <span class="list-row__meta"><span class="ltr num"><?= e(phone_local($m['phone'])) ?></span> · <?= e(role_label($m['role'])) ?><?= (int) $m['is_active'] === 1 ? '' : ' · دسترسی برداشته شده' ?></span>
          </span>
          <?php if (!$locked): ?>
            <form method="post" action="<?= e(url('panel/team/members/' . $m['user_id'])) ?>" class="row" style="--gap:6px">
              <?= csrf_field() ?>
              <label class="sr-only" for="mr-<?= (int) $m['user_id'] ?>">نقش <?= e($m['name'] ?: '') ?></label>
              <select class="select select--compact" id="mr-<?= (int) $m['user_id'] ?>" name="role">
                <option value="reception" <?= $m['role'] === 'reception' ? 'selected' : '' ?>>پذیرش</option>
                <?php if ($isOwner): ?><option value="manager" <?= $m['role'] === 'manager' ? 'selected' : '' ?>>مدیر</option><?php endif; ?>
                <option value="staff" <?= $m['role'] === 'staff' ? 'selected' : '' ?>><?= e(term('staff')) ?></option>
              </select>
              <button type="submit" class="btn btn--secondary btn--sm"><?= (int) $m['is_active'] === 1 ? 'ذخیره' : 'فعال کن' ?></button>
              <?php if ((int) $m['is_active'] === 1): ?><button type="submit" name="action" value="remove" class="btn btn--danger-ghost btn--sm" data-confirm="دسترسی <?= e($m['name'] ?: 'این فرد') ?> به پنل برداشته شود؟">برداشتن</button><?php endif; ?>
            </form>
          <?php else: ?>
            <span class="badge"><?= e(role_label($m['role'])) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<div class="card card--sunken mt-6"><div class="card__body stack stack-sm text-sm">
  <strong>چه کسی چه چیزی را می‌بیند؟</strong>
  <ul class="stack stack-xs muted" style="padding-inline-start:18px">
    <li><strong>صاحب سالن و مدیر:</strong> همه‌چیز؛ فقط صاحب سالن می‌تواند مدیر تعیین کند.</li>
    <li><strong>پذیرش:</strong> صف امروز، رزروها، مشتریان، تسویه و تأیید بیعانه؛ بدون گزارش درآمد و تنظیمات.</li>
    <li><strong><?= e(term('staff')) ?>:</strong> فقط صف و نوبت‌های خودش و فروش خودش.</li>
  </ul>
</div></div>

<dialog class="sheet" id="member-sheet" aria-labelledby="member-sheet-title">
  <div class="sheet__handle" aria-hidden="true"></div>
  <div class="sheet__head"><h2 class="sheet__title" id="member-sheet-title">دسترسی پذیرش یا مدیر</h2><button type="button" class="btn btn--ghost btn--icon" data-close aria-label="بستن"><?= icon('x') ?></button></div>
  <form method="post" action="<?= e(url('panel/team/members')) ?>" class="sheet__body stack" style="padding-inline:20px">
    <?= csrf_field() ?>
    <div class="field"><label class="field__label" for="m-name">نام</label><input class="input" id="m-name" name="name" maxlength="120"></div>
    <div class="field"><label class="field__label" for="m-phone">موبایل</label><input class="input input--ltr num" id="m-phone" name="phone" type="tel" dir="ltr" required data-numeric><?= partial('field-error', ['key' => 'member_phone']) ?></div>
    <fieldset class="field">
      <legend class="field__label mb-2">نقش</legend>
      <div class="choice-list">
        <label class="choice"><input class="choice__input" type="radio" name="role" value="reception" checked><span class="choice__card"><span class="choice__body"><span class="choice__title">پذیرش</span><span class="choice__meta">پذیرش حضوری، رزرو، تسویه و بیعانه</span></span><span class="choice__mark"><?= icon('check') ?></span></span></label>
        <?php if ($isOwner): ?><label class="choice"><input class="choice__input" type="radio" name="role" value="manager"><span class="choice__card"><span class="choice__body"><span class="choice__title">مدیر</span><span class="choice__meta">همهٔ بخش‌ها، از جمله تنظیمات و گزارش</span></span><span class="choice__mark"><?= icon('check') ?></span></span></label><?php endif; ?>
      </div>
    </fieldset>
    <button type="submit" class="btn btn--primary btn--block">دادن دسترسی</button>
  </form>
</dialog>
