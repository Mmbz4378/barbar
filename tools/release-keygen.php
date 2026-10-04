<?php

declare(strict_types=1);

/**
 * ساخت جفت‌کلید امضای انتشار (Ed25519).
 *
 *   php tools/release-keygen.php
 *
 * کلید خصوصی را فقط در GitHub ← Settings ← Secrets and variables ← Actions
 * با نام RESHEN_SIGNING_KEY بگذارید؛ هرگز در مخزن یا .env هاست نه.
 * کلید عمومی را در .env هر هاست به‌صورت UPDATE_PUBLIC_KEY=… بگذارید؛ از آن
 * به بعد بسته‌ای که با این کلید امضا نشده باشد نصب نمی‌شود.
 */

if (!function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "افزونهٔ sodium لازم است.\n");
    exit(1);
}

$pair = sodium_crypto_sign_keypair();
$secret = base64_encode(sodium_crypto_sign_secretkey($pair));
$public = base64_encode(sodium_crypto_sign_publickey($pair));

echo "کلید عمومی  (در .env هاست):\n  UPDATE_PUBLIC_KEY={$public}\n\n";
echo "کلید خصوصی (فقط در GitHub Secret به نام RESHEN_SIGNING_KEY؛ جای دیگری ذخیره نکنید):\n  {$secret}\n";
