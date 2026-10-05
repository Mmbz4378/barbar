<?php /** @var array $page @var string $html */ ?>
<article class="stack stack-lg prose-page">
  <header class="stack stack-xs">
    <h1 class="title-lg"><?= e($page['title']) ?></h1>
    <p class="text-xs muted">به‌روزرسانی: <?= e(jdate((string) $page['updated_at'], 'Y/m/d')) ?></p>
  </header>
  <div class="prose"><?= $html /* SafeMarkdown: همه‌چیز escape شده */ ?></div>
</article>
