<?php
/**
 * به‌روزرسانی سامانه.
 *
 * @var string $current
 * @var string $mode
 * @var array{start:int,end:int} $window
 * @var array $state
 * @var array|null $available
 * @var array $readiness
 * @var array $history
 * @var array $source
 */
use App\Domain\System\Updater;

$statusMeta = [
    'running' => ['در حال اجرا', 'badge--info'],
    'succeeded' => ['موفق', 'badge--success'],
    'failed' => ['ناموفق', 'badge--danger'],
    'rolled_back' => ['برگشت داده شد', 'badge--warning'],
];
$ready = !in_array(false, array_column($readiness, 'ok'), true);
$majorJump = $available !== null && (int) explode('.', $available['version'])[0] > (int) explode('.', $current)[0];
$failedBefore = $available !== null && in_array($available['version'], (array) ($state['failed'] ?? []), true);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">به‌روزرسانی سامانه</h1>
    <p class="page-head__sub">نسخهٔ نصب‌شده: <strong class="ltr"><?= e($current) ?></strong></p>
  </div>
  <div class="page-head__actions">
    <form method="post" action="<?= e(url('system/updates/check')) ?>"><?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('refresh') ?> بررسی همین حالا</button></form>
  </div>
</div>

<div class="grid grid-main-aside" style="--gap:24px">
  <div class="stack stack-lg">
    <?php if ($available !== null): ?>
      <section class="card card--accent" aria-labelledby="up-new"><div class="card__body stack">
        <div class="spread wrap">
          <h2 class="card__title" id="up-new">نسخهٔ <span class="ltr"><?= e($available['version']) ?></span> آماده است</h2>
          <?php if (!empty($available['released_at'])): ?><span class="text-sm muted"><?= e(jdate(date('Y-m-d H:i:s', strtotime((string) $available['released_at'])), 'j M Y')) ?></span><?php endif; ?>
        </div>
        <?php if (trim((string) $available['notes']) !== ''): ?>
          <div class="code-block scroll-box text-sm"><?= e($available['notes']) ?></div>
        <?php endif; ?>
        <?php if ($failedBefore): ?>
          <div class="alert alert--warning"><?= icon('alert') ?><div class="alert__body text-sm">تلاش قبلی برای نصب این نسخه ناموفق بود و نصب خودکارش متوقف شده است. جزئیات در تاریخچهٔ پایین صفحه است؛ پس از رفع مشکل، دستی نصب کنید.</div></div>
        <?php elseif ($majorJump): ?>
          <div class="alert alert--info"><?= icon('info') ?><div class="alert__body text-sm">این یک نسخهٔ اصلی تازه است؛ برای احتیاط خودکار نصب نمی‌شود. یادداشت‌ها را بخوانید و دستی نصب کنید.</div></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('system/updates/apply')) ?>" class="stack stack-sm" data-confirm="سامانه به نسخهٔ <?= e($available['version']) ?> به‌روز شود؟ چند دقیقه سایت در حالت نگه‌داری می‌رود. پیش از تغییر، فایل‌ها و (در صورت نیاز) دیتابیس پشتیبان گرفته می‌شوند و اگر خطایی رخ دهد، فایل‌ها خودکار برمی‌گردند." data-confirm-tone="neutral" data-confirm-ok="به‌روزرسانی کن">
          <?= csrf_field() ?><input type="hidden" name="version" value="<?= e($available['version']) ?>">
          <button class="btn btn--primary btn--lg" type="submit" <?= $ready ? '' : 'disabled aria-describedby="up-not-ready"' ?>><?= icon('download') ?> نصب نسخهٔ <span class="ltr"><?= e($available['version']) ?></span></button>
          <?php if (!$ready): ?><p class="text-sm danger-text" id="up-not-ready">پیش از نصب، موارد قرمز «آمادگی» را برطرف کنید.</p><?php endif; ?>
          <p class="text-xs muted">نصب در همین درخواست انجام می‌شود و ممکن است یکی‌دو دقیقه طول بکشد؛ صفحه را نبندید.</p>
        </form>
      </div></section>
    <?php else: ?>
      <div class="alert alert--success"><?= icon('circle-check') ?><div class="alert__body">
        <p class="alert__title">سامانه به‌روز است</p>
        <p class="text-sm"><?= !empty($state['checked_at']) ? 'آخرین بررسی: ' . e(jdate((string) $state['checked_at'], 'j M، H:i')) : 'هنوز بررسی نشده؛ «بررسی همین حالا» را بزنید.' ?></p>
      </div></div>
    <?php endif; ?>

    <?php if (!empty($state['error'])): ?>
      <div class="alert alert--danger"><?= icon('alert') ?><div class="alert__body"><p class="alert__title">آخرین بررسی ناموفق بود</p><p class="text-sm"><?= e((string) $state['error']) ?></p></div></div>
    <?php endif; ?>

    <section class="card" aria-labelledby="up-mode">
      <div class="card__header"><h2 class="card__title" id="up-mode">نحوهٔ به‌روزرسانی</h2></div>
      <form method="post" action="<?= e(url('system/updates/settings')) ?>" class="card__body stack">
        <?= csrf_field() ?>
        <fieldset class="stack stack-sm">
          <legend class="sr-only">حالت</legend>
          <?php foreach ([
              'auto' => ['نصب خودکار', 'هر چند ساعت بررسی می‌شود و نسخهٔ تازه در بازهٔ شبانه بی‌دخالت شما نصب می‌شود. نسخه‌های اصلی تازه (مثلاً ۱۵.۰) همیشه منتظر تأیید دستی می‌مانند.'],
              'notify' => ['فقط اعلام', 'نسخهٔ تازه این‌جا و در منو اعلام می‌شود؛ نصب با زدن دکمه.'],
              'off' => ['خاموش', 'هیچ بررسی خودکاری انجام نمی‌شود.'],
          ] as $key => [$label, $hint]): ?>
            <label class="choice">
              <input class="choice__input" type="radio" name="mode" value="<?= e($key) ?>" <?= $mode === $key ? 'checked' : '' ?>>
              <span class="choice__card"><span class="choice__body"><span class="choice__title"><?= e($label) ?></span><span class="choice__meta"><?= e($hint) ?></span></span></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <div class="form-grid form-grid--2">
          <div class="field">
            <label class="field__label" for="up-ws">نصب خودکار از ساعت</label>
            <select class="select" id="up-ws" name="window_start"><?php for ($h = 0; $h <= 23; $h++): ?><option value="<?= $h ?>" <?= $window['start'] === $h ? 'selected' : '' ?>><?= e(fa_num(sprintf('%02d:00', $h))) ?></option><?php endfor; ?></select>
          </div>
          <div class="field">
            <label class="field__label" for="up-we">تا ساعت</label>
            <select class="select" id="up-we" name="window_end"><?php for ($h = 1; $h <= 24; $h++): ?><option value="<?= $h ?>" <?= $window['end'] === $h ? 'selected' : '' ?>><?= e(fa_num(sprintf('%02d:00', $h % 24))) ?></option><?php endfor; ?></select>
          </div>
        </div>
        <p class="field__hint">ساعتی را انتخاب کنید که هیچ سالنی کار نمی‌کند. نصب خودکار با کرون انجام می‌شود؛ کرون باید هر ۵ تا ۱۵ دقیقه اجرا شود.</p>
        <div><button class="btn btn--primary" type="submit">ذخیره</button></div>
      </form>
    </section>

    <section class="section" aria-labelledby="up-history">
      <div class="section__head"><h2 class="section__title" id="up-history">تاریخچه</h2></div>
      <?php if ($history === []): ?>
        <?= partial('empty-state', ['icon' => 'list', 'title' => 'هنوز به‌روزرسانی‌ای انجام نشده', 'dashed' => true]) ?>
      <?php else: ?>
        <div class="stack stack-sm">
          <?php foreach ($history as $h): [$label, $class] = $statusMeta[$h['status']] ?? [$h['status'], '']; ?>
            <article class="card"><div class="card__body stack stack-sm">
              <div class="spread wrap">
                <strong class="ltr"><?= e($h['from_version']) ?> → <?= e($h['to_version']) ?></strong>
                <span class="cluster"><span class="badge <?= e($class) ?>"><?= e($label) ?></span><span class="badge badge--outline"><?= $h['trigger_type'] === 'auto' ? 'خودکار' : 'دستی' ?></span></span>
              </div>
              <p class="text-xs muted"><?= e(jdate((string) $h['started_at'], 'Y/m/d H:i')) ?><?= $h['finished_at'] ? ' تا ' . e(jdate((string) $h['finished_at'], 'H:i')) : '' ?></p>
              <?php if (!empty($h['message'])): ?><p class="text-sm"><?= e((string) $h['message']) ?></p><?php endif; ?>
              <?php if (!empty($h['log_text'])): ?>
                <details><summary class="text-sm link">جزئیات فنی</summary><pre class="code-block text-xs mt-2"><?= e((string) $h['log_text']) ?></pre></details>
              <?php endif; ?>
              <?php if (!empty($h['db_backup_file']) && is_file(BASE_PATH . '/' . $h['db_backup_file']) && in_array($h['status'], ['failed', 'rolled_back'], true)
                  && (App\Domain\Identity\AdminPolicy::bootstrapAllowed() || App\Domain\Identity\AdminPolicy::currentIsSuper())): ?>
                <form method="post" action="<?= e(url('system/updates/' . $h['id'] . '/restore-db')) ?>" data-confirm="دیتابیس به لحظهٔ پیش از این به‌روزرسانی برمی‌گردد و هرچه بعد از آن ثبت شده (نوبت، پرداخت) از بین می‌رود. فقط اگر سامانه خراب است این کار را بکنید. ادامه؟" data-confirm-ok="بازگردانی دیتابیس">
                  <?= csrf_field() ?><button class="btn btn--danger-ghost btn--sm" type="submit"><?= icon('refresh') ?> بازگردانی دیتابیس به پیش از این به‌روزرسانی</button>
                </form>
              <?php endif; ?>
            </div></article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="card" aria-labelledby="up-ready"><div class="card__header"><h2 class="card__title" id="up-ready">آمادگی</h2></div><div class="card__body">
      <ul class="stack stack-sm" role="list">
        <?php foreach ($readiness as $r): ?>
          <li class="row row-start" style="--gap:8px">
            <span class="icon-tile icon-tile--sm <?= $r['ok'] ? 'icon-tile--success' : 'icon-tile--danger' ?>"><?= icon($r['ok'] ? 'check' : 'x', 'icon', $r['ok'] ? 'آماده' : 'نیازمند رسیدگی') ?></span>
            <span class="stack gap-0"><span class="text-sm strong"><?= e($r['label']) ?></span><span class="text-xs muted"><?= e($r['value']) ?></span></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></section>

    <section class="card" aria-labelledby="up-src"><div class="card__header"><h2 class="card__title" id="up-src">منبع انتشار</h2></div><div class="card__body">
      <dl class="kv">
        <?php if ($source['type'] === 'url'): ?>
          <div class="kv__row"><dt>مانیفست</dt><dd class="ltr text-xs"><?= e($source['url'] ?: '—') ?></dd></div>
        <?php else: ?>
          <div class="kv__row"><dt>مخزن GitHub</dt><dd class="ltr"><?= e($source['repo']) ?></dd></div>
          <div class="kv__row"><dt>توکن دسترسی</dt><dd><?= $source['token'] ? 'تنظیم شده' : 'ندارد (مخزن عمومی)' ?></dd></div>
        <?php endif; ?>
        <div class="kv__row"><dt>کانال</dt><dd><?= $source['channel'] === 'beta' ? 'آزمایشی (beta)' : 'پایدار' ?></dd></div>
        <div class="kv__row"><dt>امضای بسته</dt><dd><?= $source['signed'] ? 'الزامی' : 'خاموش' ?></dd></div>
      </dl>
      <p class="text-xs muted mt-3">این موارد در فایل <span class="ltr">.env</span> تنظیم می‌شوند؛ راهنما: <span class="ltr">docs/updates.md</span></p>
    </div></section>

    <section class="card card--sunken"><div class="card__body stack stack-sm text-sm">
      <strong>هنگام به‌روزرسانی چه می‌شود؟</strong>
      <ol class="stack stack-xs list-bulleted">
        <li>بسته دانلود و چکیده (و امضا) بررسی می‌شود.</li>
        <li>از فایل‌هایی که عوض می‌شوند و در صورت وجود مهاجرت از دیتابیس پشتیبان گرفته می‌شود.</li>
        <li>سایت چند لحظه در حالت نگه‌داری می‌رود و فایل‌ها جایگزین می‌شوند.</li>
        <li>مهاجرت‌ها اجرا و سلامت سایت آزموده می‌شود.</li>
        <li>اگر هر مرحله شکست بخورد، فایل‌ها خودکار برمی‌گردند.</li>
      </ol>
      <p class="text-xs muted"><span class="ltr">.env</span>، عکس‌ها و پوشهٔ <span class="ltr">storage</span> هرگز دست نمی‌خورند.</p>
    </div></section>
  </aside>
</div>
