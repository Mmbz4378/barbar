<?php
/**
 * @var array $customer
 * @var array $preferences
 * @var array $fields
 * @var array $history
 * @var array $staff
 * @var int $spent
 */
$p = $preferences;
$val = static fn (string $key, mixed $fallback) => old($key, $fallback ?? '');
?>
<a class="back-link" href="<?= e(url('panel/customers')) ?>"><?= icon('chevron-start') ?> مشتریان</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= e($customer['name'] ?: 'بدون نام') ?></h1>
    <p class="page-head__sub ltr num"><?= $customer['phone'] ? e(phone_local($customer['phone'])) : 'شماره ثبت نشده' ?></p>
  </div>
  <div class="page-head__actions">
    <?php if ($customer['phone']): ?><a class="btn btn--secondary" href="tel:<?= e($customer['phone']) ?>"><?= icon('phone') ?> تماس</a><?php endif; ?>
    <a class="btn btn--primary" href="<?= e(url('panel/bookings/new')) ?>"><?= icon('calendar') ?> رزرو برای این مشتری</a>
  </div>
</div>

<div class="stats mb-6" style="--cols:4">
  <div class="stat"><span class="stat__label">مراجعه</span><span class="stat__value"><?= e(fa_num($customer['visit_count'])) ?></span></div>
  <div class="stat"><span class="stat__label">غیبت</span><span class="stat__value <?= (int) $customer['no_show_count'] > 0 ? 'warning-text' : '' ?>"><?= e(fa_num($customer['no_show_count'])) ?></span></div>
  <div class="stat"><span class="stat__label">آخرین مراجعه</span><span class="stat__value stat__value--sm"><?= $customer['last_visit_at'] ? e(jdate($customer['last_visit_at'], 'j M Y')) : '—' ?></span></div>
  <div class="stat"><span class="stat__label">مجموع پرداخت</span><span class="stat__value stat__value--sm"><?= e(toman($spent)) ?></span></div>
</div>

<div class="grid grid-2 items-start">
  <form method="post" action="<?= e(url('panel/customers/' . $customer['id'])) ?>" class="card" novalidate>
    <div class="card__header"><h2 class="card__title">مشخصات و یادداشت‌ها</h2></div>
    <div class="card__body stack">
      <?= csrf_field() ?>
      <div class="form-grid form-grid--2">
        <div class="field"><label class="field__label" for="c-name">نام</label><input class="input" id="c-name" name="name" maxlength="120" value="<?= e((string) $val('name', $customer['name'])) ?>"></div>
        <div class="field">
          <label class="field__label" for="c-phone">موبایل</label>
          <input class="input input--ltr num" id="c-phone" name="phone" type="tel" dir="ltr" value="<?= e((string) $val('phone', $customer['phone'] ? '0' . substr((string) $customer['phone'], 3) : '')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'phone']) ?>
        </div>
      </div>
      <div class="field">
        <label class="field__label" for="c-pref"><?= e(term('staff')) ?>ِ ترجیحی</label>
        <select class="select" id="c-pref" name="preferred_staff_id">
          <option value="">—</option>
          <?php foreach ($staff as $st): ?><option value="<?= (int) $st['id'] ?>" <?= (int) $customer['preferred_staff_id'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid form-grid--2">
        <?php foreach ($fields as [$column, $label, $hint]): ?>
          <div class="field"><label class="field__label" for="p-<?= e($column) ?>"><?= e($label) ?></label><input class="input" id="p-<?= e($column) ?>" name="<?= e($column) ?>" maxlength="255" placeholder="<?= e($hint) ?>" value="<?= e((string) $val($column, $p[$column] ?? '')) ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="field"><label class="field__label" for="p-last">آخرین توضیح <?= e(term('staff')) ?></label><textarea class="textarea" id="p-last" name="last_barber_said" rows="2"><?= e((string) $val('last_barber_said', $p['last_barber_said'] ?? '')) ?></textarea></div>
      <div class="field"><label class="field__label" for="c-notes">یادداشت داخلی</label><textarea class="textarea" id="c-notes" name="notes" rows="3" placeholder="فقط کارکنان سالن می‌بینند"><?= e((string) $val('notes', $customer['notes'])) ?></textarea></div>
      <div class="form-actions"><button type="submit" class="btn btn--primary">ذخیرهٔ پرونده</button></div>
    </div>
  </form>

  <section class="card" aria-labelledby="history-title">
    <div class="card__header"><h2 class="card__title" id="history-title">سابقهٔ نوبت‌ها</h2></div>
    <?php if ($history === []): ?>
      <?= partial('empty-state', ['icon' => 'calendar-days', 'title' => 'هنوز سابقه‌ای نیست']) ?>
    <?php else: ?>
      <ul class="list mt-2">
        <?php foreach ($history as $h): $when = $h['scheduled_at'] ?: ($h['queued_at'] ?: $h['created_at']); [$label, $tone] = status_meta((string) $h['status']); ?>
          <li class="list-row">
            <span class="list-row__body">
              <span class="list-row__title"><?= e($h['service_names'] ?: '—') ?></span>
              <span class="list-row__meta"><?= e(jdate($when, 'j M Y، H:i')) ?> · <?= e($h['staff_name'] ?? '') ?></span>
            </span>
            <span class="badge badge--<?= e($tone) ?>"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
