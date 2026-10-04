<?php
/**
 * بررسی آمادگی هاست — سامانه‌های بافه، رشن و داشبورد.
 *
 * چرا این فایل هست: هر سه افزونه به چند چیز نیاز دارند که روی هاست
 * اشتراکی همیشه فعال نیستند. مهم‌ترینشان php-soap است — بدون آن پیامک
 * ملی‌پیامک اصلاً کار نمی‌کند و این را فقط وقتی می‌فهمید که اولین پیامک
 * نرود. این فایل همه را یکجا و پیش از شروع می‌سنجد.
 *
 * روش استفاده:
 *   ۱. مقدار KEY را به یک رشته‌ی دلخواه عوض کنید.
 *   ۲. فایل را در ریشه‌ی سایت بگذارید.
 *   ۳. باز کنید: https://example.com/host-check.php?key=همان-رشته
 *   ۴. **بعد از خواندن نتیجه فایل را پاک کنید.**
 *
 * پاک کردنش را جدی بگیرید: این صفحه نسخه‌ی PHP، مسیرها و پیکربندی
 * سرور را نشان می‌دهد و اینها دقیقاً همان چیزهایی‌اند که یک مهاجم
 * برای انتخاب حمله لازم دارد.
 */

const KEY = 'CHANGE-ME';

if ( ! isset( $_GET['key'] ) || ! hash_equals( KEY, (string) $_GET['key'] ) ) {
	header( 'HTTP/1.1 404 Not Found' );
	exit( 'Not Found' );
}

header( 'Content-Type: text/html; charset=utf-8' );

/** یک ردیف نتیجه. */
function row( string $label, bool $ok, string $value = '', string $note = '' ): array {
	return compact( 'label', 'ok', 'value', 'note' );
}

$groups = array();

// ─── نسخه‌ی PHP ─────────────────────────────────────────────────────
$php_ok = version_compare( PHP_VERSION, '8.0', '>=' );
$groups['نسخه‌ی PHP'] = array(
	row(
		'نسخه‌ی PHP',
		$php_ok,
		PHP_VERSION,
		$php_ok ? '' : 'بیکری دست‌کم PHP 8.0 می‌خواهد. از سی‌پنل بخش Select PHP Version عوضش کنید.'
	),
);

// ─── افزونه‌های PHP ──────────────────────────────────────────────────
$ext = array(
	'soap'     => array( true,  'پیامک ملی‌پیامک با SOAP کار می‌کند. بدون این، هیچ پیامکی نمی‌رود.' ),
	'zip'      => array( true,  'خروجی اکسل گزارش‌ها با ZipArchive ساخته می‌شود.' ),
	'curl'     => array( true,  'درگاه زیبال و تماس داشبورد با سایت‌ها.' ),
	'openssl'  => array( true,  'برای HTTPS و امضای HMAC کانکتور.' ),
	'mbstring' => array( true,  'کار با متن فارسی.' ),
	'json'     => array( true,  'پایه‌ی همه‌ی APIها.' ),
	'mysqli'   => array( true,  'اتصال وردپرس به دیتابیس.' ),
	'gd'       => array( false, 'تغییر اندازه‌ی عکس محصول. اگر imagick باشد کافی است.' ),
	'imagick'  => array( false, 'جایگزین gd.' ),
	'intl'     => array( false, 'مرتب‌سازی درست متن فارسی. نبودش مرگبار نیست.' ),
);

$rows = array();
foreach ( $ext as $name => list( $required, $why ) ) {
	$has = extension_loaded( $name );
	$rows[] = row(
		$name . ( $required ? ' (ضروری)' : ' (اختیاری)' ),
		$has || ! $required,
		$has ? 'فعال' : 'غایب',
		$has ? $why : ( $required ? '⚠ ' . $why : $why )
	);
}
$groups['افزونه‌های PHP'] = $rows;

// ─── محدودیت‌ها ─────────────────────────────────────────────────────
/** «128M» را به بایت تبدیل می‌کند. */
function to_bytes( string $v ): int {
	$v    = trim( $v );
	$last = strtolower( substr( $v, -1 ) );
	$n    = (int) $v;

	switch ( $last ) {
		case 'g':
			$n *= 1024;
			// no break
		case 'm':
			$n *= 1024;
			// no break
		case 'k':
			$n *= 1024;
	}

	return $n;
}

$mem  = ini_get( 'memory_limit' );
$exec = (int) ini_get( 'max_execution_time' );
$up   = ini_get( 'upload_max_filesize' );
$post = ini_get( 'post_max_size' );

$groups['محدودیت‌ها'] = array(
	row(
		'memory_limit',
		'-1' === (string) $mem || to_bytes( (string) $mem ) >= 256 * 1024 * 1024,
		(string) $mem,
		'برای گزارش اکسل و آپلود عکس دست‌کم 256M خوب است.'
	),
	row(
		'max_execution_time',
		0 === $exec || $exec >= 60,
		0 === $exec ? 'بی‌نهایت' : $exec . ' ثانیه',
		'گزارش‌های بزرگ و ساخت فیش حقوقی وقت می‌خواهند.'
	),
	row( 'upload_max_filesize', to_bytes( (string) $up ) >= 4 * 1024 * 1024, (string) $up, 'پیوست گفتگو تا ۴ مگابایت است.' ),
	row( 'post_max_size', to_bytes( (string) $post ) >= to_bytes( (string) $up ), (string) $post, 'باید از upload_max_filesize کمتر نباشد.' ),
);

// ─── توابع غیرفعال ───────────────────────────────────────────────────
$disabled = array_filter( array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ) );
$matters  = array();
foreach ( array( 'fsockopen', 'file_get_contents', 'curl_exec', 'proc_open', 'set_time_limit' ) as $fn ) {
	if ( in_array( $fn, $disabled, true ) ) {
		$matters[] = $fn;
	}
}

$groups['توابع غیرفعال‌شده'] = array(
	row(
		'توابعی که لازم داریم',
		empty( $matters ),
		empty( $matters ) ? 'همه در دسترس' : implode( '، ', $matters ),
		empty( $matters ) ? '' : '⚠ این‌ها را از میزبان بخواهید باز کند.'
	),
);

// ─── شبکه ───────────────────────────────────────────────────────────
$net = array();

// خروجی HTTPS — بدون آن نه درگاه کار می‌کند نه پیامک.
if ( function_exists( 'curl_init' ) ) {
	$ch = curl_init( 'https://api.zibal.ir' );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_NOBODY         => true,
	) );
	curl_exec( $ch );
	$errno = curl_errno( $ch );
	$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	$net[] = row(
		'اتصال خروجی HTTPS',
		0 === $errno,
		0 === $errno ? 'برقرار (کد ' . $code . ')' : 'خطا: ' . $errno,
		'اگر بسته باشد، درگاه زیبال و پیامک هیچ‌کدام کار نمی‌کنند.'
	);

	// لوپ‌بک — داشبورد باید بتواند سایت‌های روی همین سرور را صدا بزند.
	$self = ( isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ? 'https' : 'http' )
		. '://' . ( $_SERVER['HTTP_HOST'] ?? 'localhost' ) . '/';

	$ch = curl_init( $self );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_NOBODY         => true,
		CURLOPT_FOLLOWLOCATION => true,
	) );
	curl_exec( $ch );
	$lerr = curl_errno( $ch );
	$lcode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	$net[] = row(
		'تماس سرور با خودش (loopback)',
		0 === $lerr,
		0 === $lerr ? 'برقرار (کد ' . $lcode . ')' : 'خطا: ' . $lerr,
		'داشبورد از همین راه از بافه و رشن داده می‌گیرد. بعضی هاست‌ها این را می‌بندند.'
	);
} else {
	$net[] = row( 'cURL', false, 'غایب', '⚠ بدون cURL هیچ تماس بیرونی ممکن نیست.' );
}

$groups['شبکه'] = $net;

// ─── وردپرس ─────────────────────────────────────────────────────────
$wp   = array();
$root = __DIR__;
for ( $i = 0; $i < 4 && ! file_exists( $root . '/wp-load.php' ); $i++ ) {
	$root = dirname( $root );
}

if ( file_exists( $root . '/wp-load.php' ) ) {
	$ver = 'نامشخص';
	$vf  = $root . '/wp-includes/version.php';
	if ( file_exists( $vf ) && preg_match( "/\\\$wp_version\s*=\s*'([^']+)'/", (string) file_get_contents( $vf ), $m ) ) {
		$ver = $m[1];
	}

	$wp[] = row( 'وردپرس', version_compare( $ver, '6.0', '>=' ) || 'نامشخص' === $ver, $ver, 'بیکری دست‌کم ۶.۰ می‌خواهد.' );

	$uploads = $root . '/wp-content/uploads';
	$wp[]    = row(
		'پوشه‌ی uploads قابل نوشتن',
		is_dir( $uploads ) && is_writable( $uploads ),
		is_dir( $uploads ) ? ( is_writable( $uploads ) ? 'بله' : 'خیر' ) : 'وجود ندارد',
		'برای عکس محصول و پیوست گفتگو.'
	);
} else {
	$wp[] = row( 'وردپرس', false, 'پیدا نشد', 'این فایل را کنار wp-load.php بگذارید.' );
}

$groups['وردپرس'] = $wp;

// ─── شمارش ──────────────────────────────────────────────────────────
$fail = 0;
foreach ( $groups as $rows ) {
	foreach ( $rows as $r ) {
		if ( ! $r['ok'] ) {
			$fail++;
		}
	}
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>بررسی آمادگی هاست</title>
<style>
  :root{--bg:#f6f7f9;--card:#fff;--ink:#1c1f24;--dim:#6b7280;--line:#e5e7eb;
        --ok:#15803d;--okbg:#f0fdf4;--bad:#b91c1c;--badbg:#fef2f2}
  @media (prefers-color-scheme:dark){
    :root{--bg:#111316;--card:#1a1d21;--ink:#e8eaed;--dim:#9aa0a6;--line:#2a2e33;
          --ok:#4ade80;--okbg:#0f2417;--bad:#f87171;--badbg:#2a1416}
  }
  *{box-sizing:border-box}
  body{margin:0;padding:16px;background:var(--bg);color:var(--ink);
       font:15px/1.7 Tahoma,system-ui,sans-serif}
  .wrap{max-width:760px;margin:0 auto}
  h1{font-size:20px;margin:0 0 4px}
  .sub{color:var(--dim);font-size:13px;margin:0 0 20px}
  .verdict{padding:14px 16px;border-radius:10px;margin:0 0 20px;font-weight:700}
  .verdict.ok{background:var(--okbg);color:var(--ok)}
  .verdict.bad{background:var(--badbg);color:var(--bad)}
  h2{font-size:15px;margin:22px 0 8px;color:var(--dim);font-weight:700}
  .card{background:var(--card);border:1px solid var(--line);border-radius:10px;overflow:hidden}
  .r{display:flex;gap:10px;padding:11px 14px;border-bottom:1px solid var(--line);
     align-items:flex-start;flex-wrap:wrap}
  .r:last-child{border-bottom:0}
  .mark{flex:0 0 auto;font-weight:700;width:18px}
  .mark.y{color:var(--ok)} .mark.n{color:var(--bad)}
  .lab{flex:1 1 180px;min-width:0;font-weight:600}
  .val{flex:0 0 auto;color:var(--dim);font-family:monospace;direction:ltr;unicode-bidi:plaintext}
  .note{flex:1 1 100%;color:var(--dim);font-size:13px;padding-inline-start:28px}
  footer{margin:24px 0 8px;padding:12px 14px;border-radius:10px;
         background:var(--badbg);color:var(--bad);font-weight:700;font-size:14px}
</style>
</head>
<body>
<div class="wrap">
  <h1>بررسی آمادگی هاست</h1>
  <p class="sub">
    <?php echo htmlspecialchars( $_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>
    · <?php echo htmlspecialchars( date( 'Y-m-d H:i' ), ENT_QUOTES, 'UTF-8' ); ?>
  </p>

  <div class="verdict <?php echo 0 === $fail ? 'ok' : 'bad'; ?>">
    <?php echo 0 === $fail
      ? '✓ همه‌ی بررسی‌ها قبول شد — این هاست آماده است.'
      : '✗ ' . $fail . ' مورد نیاز به رسیدگی دارد (پایین با ✗ مشخص شده‌اند).'; ?>
  </div>

  <?php foreach ( $groups as $title => $rows ) : ?>
    <h2><?php echo htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ); ?></h2>
    <div class="card">
      <?php foreach ( $rows as $r ) : ?>
        <div class="r">
          <span class="mark <?php echo $r['ok'] ? 'y' : 'n'; ?>"><?php echo $r['ok'] ? '✓' : '✗'; ?></span>
          <span class="lab"><?php echo htmlspecialchars( $r['label'], ENT_QUOTES, 'UTF-8' ); ?></span>
          <?php if ( '' !== $r['value'] ) : ?>
            <span class="val"><?php echo htmlspecialchars( $r['value'], ENT_QUOTES, 'UTF-8' ); ?></span>
          <?php endif; ?>
          <?php if ( '' !== $r['note'] ) : ?>
            <span class="note"><?php echo htmlspecialchars( $r['note'], ENT_QUOTES, 'UTF-8' ); ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <footer>یادتان باشد این فایل را بعد از خواندن نتیجه از سرور پاک کنید.</footer>
</div>
</body>
</html>
