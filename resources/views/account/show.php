<?php
/**
 * «حساب من» — هم در قالب پلتفرم (مدیر کل) و هم در قالب ورود (بقیه).
 *
 * @var array       $user
 * @var bool        $forced       مدیر رمز موقت داده؛ تا عوضش نکند جای دیگری نمی‌رود
 * @var bool        $hasPassword
 * @var array       $events
 * @var int         $minLength
 * @var string|null $backUrl
 */
use App\Domain\Identity\LoginEvents;

$inCard = !empty($wideCard); // قالب ورود خودش کارت دارد
?>
<?php if (!$inCard): ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">حساب من</h1>
    <p class="page-head__sub">نام، نام کاربری، رمز و ورودهای اخیر</p>
  </div>
</div>
<div class="stack stack-lg container-md">
<?php else: ?>
<div class="stack stack-lg">
  <div class="cluster justify-between">
    <h1 class="title-md">حساب من</h1>
    <?php if ($backUrl !== null && !$forced): ?><a class="btn btn--ghost btn--sm" href="<?= e(url(ltrim($backUrl, '/'))) ?>"><?= icon('chevron-start') ?> بازگشت به پنل</a><?php endif; ?>
  </div>
<?php endif; ?>

  <?php if ($forced): ?>
    <div class="alert alert--warning" role="status"><?= icon('lock') ?><div class="alert__body"><p class="alert__title">پیش از ادامه، رمز تازه بگذارید</p><p>رمز فعلی را مدیر برایتان تعیین کرده است. رمزی بگذارید که فقط خودتان می‌دانید.</p></div></div>
  <?php endif; ?>

  <?php if (!$forced): ?>
  <section class="<?= $inCard ? 'stack' : 'card card__body stack' ?>" aria-labelledby="acc-profile">
    <h2 class="title-sm" id="acc-profile">مشخصات</h2>
    <form method="post" action="<?= e(url('account/profile')) ?>" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="name">نام و نام خانوادگی</label>
        <input class="input" id="name" name="name" required maxlength="120" autocomplete="name" value="<?= e((string) old('name', $user['name'] ?? '')) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'name']) ?>
      </div>
      <div class="field">
        <span class="field__label">شمارهٔ موبایل</span>
        <p class="ltr num strong"><?= e(phone_local((string) $user['phone'])) ?></p>
        <p class="field__hint">برای تغییر شماره به مدیر سامانه بگویید؛ کد ورود و بازیابی رمز به همین شماره می‌آید.</p>
      </div>
      <div class="field">
        <label class="field__label" for="username">نام کاربری <span class="field__optional">(اختیاری)</span></label>
        <input class="input input--ltr" id="username" name="username" maxlength="40" dir="ltr" autocapitalize="none" spellcheck="false" autocomplete="username" value="<?= e((string) old('username', $user['username'] ?? '')) ?>" aria-describedby="username-hint<?= field_error('username') ? ' username-error' : '' ?>" <?= field_error('username') ? 'aria-invalid="true"' : '' ?>>
        <p class="field__hint" id="username-hint">به‌جای شمارهٔ موبایل هم می‌توانید با این وارد شوید. حروف لاتین، عدد، نقطه، خط تیره.</p>
        <?= partial('field-error', ['key' => 'username']) ?>
      </div>
      <div><button class="btn btn--primary" type="submit">ذخیرهٔ مشخصات</button></div>
    </form>
  </section>
  <?php endif; ?>

  <section class="<?= $inCard ? 'stack' : 'card card__body stack' ?>" id="password" aria-labelledby="acc-password">
    <h2 class="title-sm" id="acc-password"><?= $hasPassword && !$forced ? 'تغییر رمز' : 'تعیین رمز' ?></h2>
    <?php if (!$hasPassword): ?><p class="text-sm muted">هنوز رمزی ندارید و با کد پیامکی وارد می‌شوید. با تعیین رمز، بدون پیامک هم می‌توانید وارد شوید.</p><?php endif; ?>
    <form method="post" action="<?= e(url('account/password')) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="text" name="username_hint" value="<?= e((string) ($user['username'] ?: $user['phone'])) ?>" autocomplete="username" hidden>
      <?php if ($hasPassword && !$forced): ?>
        <div class="field">
          <label class="field__label" for="current_password">رمز فعلی</label>
          <input class="input input--ltr" id="current_password" type="password" name="current_password" autocomplete="current-password" required dir="ltr" <?= field_error('current_password') ? 'aria-invalid="true" aria-describedby="current_password-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'current_password']) ?>
        </div>
      <?php endif; ?>
      <div class="field">
        <label class="field__label" for="new-password">رمز تازه</label>
        <input class="input input--ltr" id="new-password" type="password" name="password" autocomplete="new-password" required minlength="<?= (int) $minLength ?>" dir="ltr" aria-describedby="password-hint<?= field_error('password') ? ' password-error' : '' ?>" <?= field_error('password') ? 'aria-invalid="true"' : '' ?> <?= $forced ? 'autofocus' : '' ?>>
        <p class="field__hint" id="password-hint">دست‌کم <?= e(fa_num($minLength)) ?> نویسه؛ ترکیب حرف و عدد امن‌تر است. با ذخیره، از دستگاه‌های دیگر خارج می‌شوید.</p>
        <?= partial('field-error', ['key' => 'password']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="password_confirm">تکرار رمز تازه</label>
        <input class="input input--ltr" id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" required dir="ltr">
      </div>
      <div><button class="btn btn--primary" type="submit">ذخیرهٔ رمز</button></div>
    </form>
  </section>

  <?php if (!$forced): ?>
  <section class="<?= $inCard ? 'stack' : 'card card__body stack' ?>" aria-labelledby="acc-logins">
    <div class="cluster justify-between">
      <h2 class="title-sm" id="acc-logins">ورودهای اخیر</h2>
      <form method="post" action="<?= e(url('account/sessions')) ?>" data-confirm="از همهٔ دستگاه‌های دیگر خارج می‌شوید؛ این دستگاه وارد می‌ماند." data-confirm-tone="neutral" data-confirm-ok="خروج از بقیه">
        <?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('logout') ?> خروج از دستگاه‌های دیگر</button>
      </form>
    </div>
    <?php if ($events === []): ?>
      <p class="text-sm muted">هنوز ورودی ثبت نشده است.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">زمان</th><th scope="col">روش</th><th scope="col">نتیجه</th><th scope="col">نشانی IP</th></tr></thead>
          <tbody>
            <?php foreach ($events as $ev): ?>
              <tr>
                <td data-label="زمان" class="num"><?= e(jdate((string) $ev['created_at'])) ?></td>
                <td data-label="روش"><?= e(LoginEvents::methodLabel((string) $ev['method'])) ?></td>
                <td data-label="نتیجه"><?php if ((int) $ev['success'] === 1): ?><span class="badge badge--success">موفق</span><?php else: ?><span class="badge badge--danger">ناموفق</span> <span class="text-sm muted"><?= e(LoginEvents::reasonLabel($ev['reason'] ?? null)) ?></span><?php endif; ?></td>
                <td data-label="نشانی IP" class="ltr num"><?= e((string) ($ev['ip_address'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="text-xs muted">ورودی را نمی‌شناسید؟ رمز را عوض کنید و از دستگاه‌های دیگر خارج شوید.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($forced): ?><a class="btn btn--ghost" href="<?= e(url('logout')) ?>">خروج</a><?php endif; ?>
</div>
