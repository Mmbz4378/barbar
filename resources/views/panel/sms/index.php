<?php
/**
 * الگوهای پیامک — متن آماده برای ثبت در سامانهٔ اپراتور.
 *
 * @var array $rows
 * @var string $driver
 * @var string $provider
 * @var bool $canRegister
 * @var bool $dedicatedLine
 */
$configured = 0;
foreach ($rows as $r) { if ($r['melipayamak'] !== '' || $r['kavenegar'] !== '') { $configured++; } }
$total = count($rows);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">پیامک‌ها</h1>
    <p class="page-head__sub">روی خط خدماتی، اپراتور فقط متنی را تحویل می‌دهد که از پیش به‌عنوان «الگو» ثبت و تأیید شده باشد.</p>
  </div>
</div>

<div class="stats mb-6" style="--cols:3">
  <div class="stat <?= $configured === $total ? 'stat--accent' : '' ?>"><span class="stat__label"><?= icon('message') ?> الگوهای آماده</span><span class="stat__value"><?= e(fa_num($configured)) ?> از <?= e(fa_num($total)) ?></span></div>
  <div class="stat"><span class="stat__label"><?= icon('cog') ?> سرویس ارسال</span><span class="stat__value stat__value--sm ltr"><?= e($driver) ?></span><?php if ($driver === 'log'): ?><span class="stat__hint">حالت آزمایشی؛ پیامک واقعی نمی‌رود</span><?php endif; ?></div>
  <div class="stat"><span class="stat__label"><?= icon('info') ?> خط ارسال</span><span class="stat__value stat__value--sm"><?= $dedicatedLine ? 'اختصاصی' : 'خدماتی' ?></span><span class="stat__hint"><?= $dedicatedLine ? 'الگوی تنظیم‌نشده با متن آزاد می‌رود' : 'الگوی تنظیم‌نشده فرستاده نمی‌شود' ?></span></div>
</div>

<?php if (!$canRegister && $provider === 'melipayamak'): ?>
  <div class="alert alert--info mb-4"><?= icon('info') ?><div class="alert__body">برای ثبت خودکار الگوها، <span class="ltr">SMS_MELIPAYAMAK_USERNAME</span> و <span class="ltr">SMS_MELIPAYAMAK_PASSWORD</span> را در فایل <span class="ltr">.env</span> بگذارید. تا آن زمان متن‌ها را دستی در پنل اپراتور ثبت کنید.</div></div>
<?php endif; ?>

<div class="stack">
  <?php foreach ($rows as $code => $r): $ready = $r['melipayamak'] !== '' || $r['kavenegar'] !== ''; ?>
    <section class="card" aria-labelledby="sms-<?= e($code) ?>">
      <div class="card__header card__header--divided">
        <h2 class="card__title row" id="sms-<?= e($code) ?>"><span class="dot <?= $ready ? 'dot--success' : 'dot--warning' ?>"></span><?= e($r['title']) ?></h2>
        <div class="cluster">
          <?php if ($r['critical']): ?><span class="badge badge--accent">حیاتی</span><?php endif; ?>
          <span class="badge badge--<?= $ready ? 'success' : 'warning' ?>"><?= $ready ? 'تنظیم شده' : 'تنظیم نشده' ?></span>
        </div>
      </div>
      <div class="card__body stack stack-md">
        <p class="text-sm muted"><?= e($r['note']) ?></p>
        <div class="stack stack-xs">
          <div class="spread"><span class="field__label">متن الگو (متغیرهای <?= e($provider === 'kavenegar' ? 'کاوه‌نگار' : 'ملی‌پیامک') ?>)</span><button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($r['providerPattern']) ?>"><?= icon('copy') ?> کپی</button></div>
          <pre class="card card--sunken text-sm" style="margin:0;padding:12px;white-space:pre-wrap;font-family:inherit"><?= e($r['providerPattern']) ?></pre>
        </div>
        <div class="cluster text-sm"><span class="muted">ترتیب متغیرها:</span><?php foreach ($r['vars'] as $i => $v): ?><span class="badge badge--outline"><?= e(fa_num($i + 1)) ?>. <span class="ltr"><?= e($v) ?></span></span><?php endforeach; ?></div>
        <?php if ($r['registered'] !== null): ?>
          <div class="alert alert--success"><?= icon('circle-check') ?><div class="alert__body stack stack-xs">
            <span>ثبت شد — شناسه <strong class="ltr"><?= e($r['registered']['body_id']) ?></strong>. تأیید اپراتور ممکن است چند روز طول بکشد. این خط را در <span class="ltr">.env</span> بگذارید:</span>
            <div class="copy-field"><code><?= e($r['envKey']) ?>=<?= e($r['registered']['body_id']) ?></code><button type="button" class="btn btn--secondary btn--sm" data-copy="<?= e($r['envKey'] . '=' . $r['registered']['body_id']) ?>">کپی</button></div>
          </div></div>
        <?php else: ?>
          <p class="text-sm muted">شناسهٔ الگو را اینجا بگذارید: <span class="ltr strong"><?= e($r['envKey']) ?>=…</span></p>
        <?php endif; ?>
        <?php if ($canRegister): ?>
          <form method="post" action="<?= e(url('panel/sms/register')) ?>">
            <?= csrf_field() ?><input type="hidden" name="code" value="<?= e($code) ?>">
            <button type="submit" class="btn btn--secondary"><?= $r['registered'] !== null ? 'ثبت دوباره در ملی‌پیامک' : 'ثبت در ملی‌پیامک' ?></button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
