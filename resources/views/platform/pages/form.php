<?php /** @var array $page @var string|null $preview */ $isNew = $page['id'] === null; $v = static fn (string $k) => old($k, (string) ($page[$k] ?? '')); ?>
<a class="back-link" href="<?= e(url('platform/pages')) ?>"><?= icon('chevron-start') ?> همهٔ صفحه‌ها</a>
<div class="page-head">
  <div class="page-head__text"><h1 class="page-head__title"><?= $isNew ? 'صفحهٔ تازه' : e($page['title']) ?></h1></div>
  <?php if (!$isNew && (int) $page['is_published'] === 1): ?><div class="page-head__actions"><a class="btn btn--secondary" href="<?= e(url('p/' . $page['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> نمایش در سایت</a></div><?php endif; ?>
</div>
<div class="grid grid-main-aside" style="--gap:24px">
  <form method="post" action="<?= e(url($isNew ? 'platform/pages' : 'platform/pages/' . $page['id'])) ?>" class="stack stack-lg">
    <?= csrf_field() ?>
    <section class="card"><div class="card__body stack">
      <div class="field">
        <label class="field__label" for="title">عنوان</label>
        <input class="input" id="title" name="title" required maxlength="150" value="<?= e((string) $v('title')) ?>" <?= field_error('title') ? 'aria-invalid="true" aria-describedby="title-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'title']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="slug">نشانی</label>
        <div class="input-group" dir="ltr"><span class="input-group__addon">/p/</span><input class="input" id="slug" name="slug" required maxlength="80" dir="ltr" autocapitalize="none" spellcheck="false" value="<?= e((string) $v('slug')) ?>" <?= field_error('slug') ? 'aria-invalid="true" aria-describedby="slug-error"' : '' ?>></div>
        <?= partial('field-error', ['key' => 'slug']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="body">متن</label>
        <textarea class="textarea" id="body" name="body" rows="18" required aria-describedby="body-hint<?= field_error('body') ? ' body-error' : '' ?>" <?= field_error('body') ? 'aria-invalid="true"' : '' ?>><?= e((string) $v('body')) ?></textarea>
        <p class="field__hint" id="body-hint">قالب‌بندی ساده: خطِ <span class="ltr">## عنوان</span> سرتیتر می‌شود، <span class="ltr">- مورد</span> فهرست، <span class="ltr">**متن**</span> پررنگ، و <span class="ltr">[متن](https://…)</span> پیوند. یک خط خالی بین پاراگراف‌ها.</p>
        <?= partial('field-error', ['key' => 'body']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="meta_description">توضیح برای گوگل <span class="field__optional">(اختیاری)</span></label>
        <input class="input" id="meta_description" name="meta_description" maxlength="300" value="<?= e((string) $v('meta_description')) ?>">
      </div>
    </div></section>
    <section class="card"><div class="card__body stack">
      <label class="check"><input type="checkbox" name="is_published" value="1" <?= (int) old('is_published', (string) $page['is_published']) === 1 ? 'checked' : '' ?>><span class="check__text"><span class="strong">منتشر شود</span><span class="check__hint">پیش‌نویس فقط اینجا دیده می‌شود.</span></span></label>
      <label class="check"><input type="checkbox" name="show_in_footer" value="1" <?= (int) old('show_in_footer', (string) $page['show_in_footer']) === 1 ? 'checked' : '' ?>><span>پیوندش در پانویس سایت بیاید</span></label>
      <div class="field w-sm"><label class="field__label" for="sort_order">ترتیب در پانویس</label><input class="input num" id="sort_order" name="sort_order" inputmode="numeric" value="<?= e((string) $v('sort_order')) ?>" data-numeric></div>
    </div></section>
    <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
  </form>
  <aside class="stack">
    <?php if (!empty($preview)): ?>
      <section class="card"><div class="card__body stack"><h2 class="card__title">پیش‌نمایش ذخیره‌شده</h2><div class="prose text-sm"><?= $preview ?></div></div></section>
    <?php endif; ?>
    <?php if (!$isNew): ?>
      <form method="post" action="<?= e(url('platform/pages/' . $page['id'] . '/delete')) ?>" class="card" data-confirm="صفحهٔ «<?= e($page['title']) ?>» حذف شود؟ پیوندهای آن از کار می‌افتند."><div class="card__body">
        <?= csrf_field() ?><button class="btn btn--danger-ghost" type="submit"><?= icon('trash') ?> حذف صفحه</button>
      </div></form>
    <?php endif; ?>
  </aside>
</div>
