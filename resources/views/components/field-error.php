<?php /** @var string $key */ $message = field_error($key); ?>
<?php if ($message !== null): ?><p class="field__error" id="<?= e($key) ?>-error"><?= icon('alert') ?><span><?= e($message) ?></span></p><?php endif; ?>
