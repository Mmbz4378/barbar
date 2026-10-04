<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * درخواست هم‌زمانِ زیاد روی یک سالن — قفل در زمان معقول آزاد نشد.
 *
 * از RuntimeException ارث می‌برد تا کنترلرها، که همین را می‌گیرند و پیامش را
 * نشان می‌دهند، به‌جای متن خام «SQLSTATE … Lock wait timeout» این پیام را
 * نشان دهند.
 */
final class LockConflict extends RuntimeException
{
    public function __construct(string $message = 'الان درخواست‌های زیادی هم‌زمان به این سالن می‌رسد. چند لحظهٔ دیگر دوباره تلاش کنید.')
    {
        parent::__construct($message);
    }
}
