<?php
/**
 * برچسب دکمهٔ «ادامه» بر اساس گام بعدی همین سالن.
 *
 * @var array $stepper
 */
$nextLabel = 'مرور و ثبت نوبت';
foreach ($stepper as $i => $s) {
    if ($s['state'] === 'current' && isset($stepper[$i + 1])) {
        $next = $stepper[$i + 1];
        $nextLabel = $next['key'] === 'details' ? 'مرور و ثبت نوبت' : 'ادامه: انتخاب ' . $next['label'];
    }
}
