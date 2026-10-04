<?php
/**
 * کارت نوبت.
 *
 * @var array $salon
 * @var array $appointment  بخش اول
 * @var array $parts        همهٔ بخش‌ها (برای رزرو چندنفره بیش از یکی)
 * @var array $items        خدمات به تفکیک شناسهٔ نوبت
 * @var array $staffNames
 * @var ?array $display
 * @var int $deposit
 * @var bool $depositPaid
 * @var array{ok:bool,reason:?string} $cancellation
 * @var bool $justBooked
 * @var ?array $payment
 * @var bool $onlinePay
 * @var ?array $pendingPayment
 */
use App\Support\JalaliCalendar;

$status = $appointment['status'];
$live = in_array($status, ['confirmed', 'queued', 'in_chair', 'pending'], true);
$start = !empty($appointment['scheduled_at']) ? new DateTimeImmutable((string) $appointment['scheduled_at']) : null;
$total = 0;
foreach ($items as $rows) { $total += array_sum(array_map(static fn ($r) => (int) $r['price'], $rows)); }
$hasFrom = false;
foreach ($items as $rows) { foreach ($rows as $r) { $hasFrom = $hasFrom || ($r['price_type'] ?? '') === 'from'; } }

$tone = match ($status) {
    'cancelled' => 'danger',
    'no_show', 'completed' => 'muted',
    'pending' => 'warning',
    default => '',
};
?>
<div id="ticket" <?= $live ? 'data-auto-refresh="30" data-refresh-ids="ticket"' : '' ?>>

<?php if ($justBooked): ?>
  <div class="alert alert--success mb-4" role="status"><?= icon('circle-check') ?><div class="alert__body"><span class="alert__title"><?= $status === 'pending' ? 'نوبت شما نگه داشته شد' : 'نوبت شما ثبت شد' ?></span>این صفحه را نگه دارید یا به تقویم اضافه کنید؛ لینکش در پیامک هم می‌آید.</div></div>
<?php endif; ?>

<article class="card ticket" aria-labelledby="ticket-title">
  <div class="ticket__status <?= $tone !== '' ? 'ticket__status--' . $tone : '' ?>" id="ticket-status" role="status" aria-live="polite">
    <?php if ($status === 'in_chair'): ?>
      <span class="badge badge--success"><span class="dot dot--live"></span> در حال انجام</span>
      <p class="ticket__big"><?= e(term('in_service_customer')) ?></p>
    <?php elseif ($status === 'completed'): ?>
      <span class="icon-tile icon-tile--success"><?= icon('circle-check') ?></span>
      <p class="ticket__big">خدمت انجام شد</p>
      <p class="muted">ممنون از انتخاب شما.</p>
    <?php elseif ($status === 'cancelled'): ?>
      <span class="icon-tile icon-tile--danger"><?= icon('calendar-x') ?></span>
      <p class="ticket__big">این نوبت لغو شده است</p>
      <?php if (!empty($appointment['cancel_reason'])): ?><p class="muted text-sm"><?= e($appointment['cancel_reason']) ?></p><?php endif; ?>
    <?php elseif ($status === 'no_show'): ?>
      <p class="ticket__big">این نوبت به‌عنوان غیبت ثبت شد</p>
    <?php elseif ($status === 'pending'): ?>
      <span class="badge badge--warning"><?= icon('hourglass') ?> منتظر بیعانه</span>
      <p class="ticket__big">نوبت تا تأیید بیعانه نگه داشته شده</p>
    <?php elseif ($display !== null && $status === 'queued'): ?>
      <span class="eyebrow">زمان تقریبی نوبت شما</span>
      <p class="ticket__big accent-text"><?= e($display['text']) ?></p>
      <?php if (!empty($display['rough'])): ?><p class="text-sm warning-text">تخمین تقریبی است</p><?php endif; ?>
    <?php else: ?>
      <span class="badge badge--accent"><?= icon('circle-check') ?> نوبت تأییدشده</span>
      <?php if ($start !== null): ?>
        <p class="ticket__big"><?= e(JalaliCalendar::relativeDate($start)) ?>، ساعت <?= e(fa_time($start->format('H:i'))) ?></p>
        <p class="muted"><?= e(JalaliCalendar::humanDate($start, true)) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card__body stack">
    <h1 class="sr-only" id="ticket-title">نوبت در <?= e($salon['name']) ?></h1>

    <?php if ($status === 'pending' && $deposit > 0 && !$depositPaid): ?>
      <div class="alert alert--warning">
        <?= icon('wallet') ?>
        <div class="alert__body stack stack-sm">
          <span class="alert__title">بیعانه: <?= e(toman($deposit)) ?></span>
          <span>مبلغ را به کارت زیر واریز کنید و رسید را برای سالن بفرستید<?= !empty($salon['phone']) ? ' (' . e(fa_num($salon['phone'])) . ')' : '' ?>. پس از تأیید سالن، نوبت قطعی می‌شود.</span>
          <?php if (!empty($salon['deposit_card_number'])): ?>
            <div class="copy-field">
              <code><?= e(trim(chunk_split(preg_replace('/\D/', '', (string) $salon['deposit_card_number']), 4, ' '))) ?></code>
              <button type="button" class="btn btn--secondary btn--sm" data-copy="<?= e(preg_replace('/\D/', '', (string) $salon['deposit_card_number'])) ?>"><?= icon('copy') ?> کپی</button>
            </div>
            <?php if (!empty($salon['deposit_card_holder'])): ?><span class="text-sm">به نام <?= e($salon['deposit_card_holder']) ?></span><?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($appointment['hold_expires_at'])): ?>
            <span class="text-sm">مهلت پرداخت: تا ساعت <?= e(fa_time(substr((string) $appointment['hold_expires_at'], 11, 5))) ?> <?= e(JalaliCalendar::relativeDate(new DateTimeImmutable((string) $appointment['hold_expires_at']))) ?>؛ پس از آن نوبت خودکار آزاد می‌شود.</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <dl class="kv">
      <div class="kv__row"><dt>سالن</dt><dd><?= e($salon['name']) ?></dd></div>
      <?php if ($start !== null && $status !== 'confirmed'): ?>
        <div class="kv__row"><dt>زمان</dt><dd><?= e(JalaliCalendar::humanDate($start)) ?>، ساعت <?= e(fa_time($start->format('H:i'))) ?></dd></div>
      <?php endif; ?>
      <?php if (count($parts) === 1 && !empty($appointment['staff_id'])): ?>
        <div class="kv__row"><dt><?= e(term('staff')) ?></dt><dd><?= e($staffNames[(int) $appointment['staff_id']] ?? '') ?></dd></div>
      <?php endif; ?>
      <?php if (!empty($salon['address'])): ?>
        <div class="kv__row"><dt>نشانی</dt><dd><?= e(join_parts([$salon['city'] ?? '', $salon['address']])) ?></dd></div>
      <?php endif; ?>
    </dl>

    <?php if (count($parts) > 1): ?>
      <section aria-label="بخش‌های نوبت">
        <h2 class="title-xs mb-2">ترتیب خدمات</h2>
        <ol class="timeline">
          <?php foreach ($parts as $part): ?>
            <li class="timeline__item">
              <span class="timeline__time"><?= e(fa_time(substr((string) $part['scheduled_at'], 11, 5))) ?></span>
              <span class="timeline__line" aria-hidden="true"></span>
              <div class="timeline__body">
                <div class="strong"><?= e(implode('، ', array_column($items[(int) $part['id']] ?? [], 'service_name'))) ?></div>
                <div class="text-sm muted"><?= e($staffNames[(int) $part['staff_id']] ?? '') ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>

    <dl class="kv">
      <?php foreach ($parts as $part): foreach ($items[(int) $part['id']] ?? [] as $it): ?>
        <div class="kv__row"><dt><?= e($it['service_name']) ?></dt><dd class="num"><?= e(price_text((int) $it['price'], (string) $it['price_type'])) ?></dd></div>
      <?php endforeach; endforeach; ?>
      <div class="kv__row kv__row--total"><dt>جمع<?= $hasFrom ? ' (حداقل)' : '' ?></dt><dd class="num"><?= e(price_text($total, $hasFrom ? 'from' : 'fixed')) ?></dd></div>
    </dl>

    <?php if (!empty($appointment['customer_note'])): ?>
      <p class="text-sm muted"><?= icon('message', 'icon') ?> توضیح شما: <?= e($appointment['customer_note']) ?></p>
    <?php endif; ?>

    <?php if (!empty($payment)): ?>
      <div class="alert alert--success"><?= icon('receipt') ?><div class="alert__body">تسویه ثبت شده: <?= e(toman((int) $payment['amount'])) ?></div></div>
    <?php elseif ($status === 'completed' && $onlinePay): ?>
      <form method="post" action="<?= e(url('q/' . $appointment['public_token'] . '/pay')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn--primary btn--block btn--lg"><?= icon('card') ?> پرداخت آنلاین <?= e(toman($total)) ?></button>
      </form>
      <?php if (!empty($pendingPayment)): ?>
        <a class="btn btn--secondary btn--block" href="<?= e(url('payments/callback') . '?Authority=' . rawurlencode($pendingPayment['authority'])) ?>">بررسی نتیجهٔ پرداخت قبلی</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card__footer">
    <?php if ($start !== null && in_array($status, ['confirmed', 'pending'], true)): ?>
      <a class="btn btn--secondary" href="<?= e(url('q/' . $appointment['public_token'] . '/calendar.ics')) ?>"><?= icon('calendar') ?> افزودن به تقویم</a>
    <?php endif; ?>
    <?php if (!empty($salon['phone'])): ?>
      <a class="btn btn--ghost" href="tel:<?= e($salon['phone']) ?>"><?= icon('phone') ?> تماس با سالن</a>
    <?php endif; ?>
    <?php if ($salon['map_lat'] !== null && $salon['map_lng'] !== null): ?>
      <a class="btn btn--ghost" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e((string) $salon['map_lat']) ?>&amp;mlon=<?= e((string) $salon['map_lng']) ?>#map=17/<?= e((string) $salon['map_lat']) ?>/<?= e((string) $salon['map_lng']) ?>"><?= icon('navigation') ?> مسیریابی</a>
    <?php endif; ?>
  </div>
</article>

<div class="stack mt-4">
  <?php if ($cancellation['ok']): ?>
    <form method="post" action="<?= e(url('q/' . $appointment['public_token'] . '/cancel')) ?>" data-confirm="<?= e(count($parts) > 1 ? 'همهٔ بخش‌های این نوبت لغو شوند؟' : 'این نوبت لغو شود؟') ?>" data-confirm-ok="بله، لغو شود">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--danger-soft btn--block">لغو نوبت</button>
    </form>
  <?php elseif ($cancellation['reason'] !== null): ?>
    <p class="text-sm muted center"><?= e($cancellation['reason']) ?></p>
  <?php endif; ?>
  <div class="btn-row" style="justify-content:center">
    <a class="btn btn--ghost" href="<?= e(url('me')) ?>"><?= icon('calendar-days') ?> همهٔ نوبت‌های من</a>
    <?php if (in_array($status, ['completed', 'cancelled', 'no_show'], true)): ?>
      <a class="btn btn--ghost" href="<?= e(url('s/' . $salon['slug'])) ?>"><?= icon('refresh') ?> رزرو دوباره</a>
    <?php endif; ?>
  </div>
</div>
</div>
