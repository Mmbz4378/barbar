<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Core\Request;
use App\Support\Jalali;
use App\Support\Now;
use DateTimeImmutable;

/**
 * بازهٔ زمانی گزارش: پیش‌فرض‌های شمسی (امروز، ۷ و ۳۰ روز، این ماه، ماه
 * قبل، امسال) یا بازهٔ دلخواه. بازهٔ قبلیِ هم‌اندازه برای مقایسه («نسبت به
 * دورهٔ قبل») هم از همین‌جا می‌آید.
 */
final class ReportRange
{
    public const PRESETS = [
        'today' => 'امروز',
        '7d' => '۷ روز اخیر',
        '30d' => '۳۰ روز اخیر',
        '90d' => '۹۰ روز اخیر',
        'month' => 'این ماه',
        'last_month' => 'ماه قبل',
        'year' => 'امسال',
        'custom' => 'بازهٔ دلخواه',
    ];

    /** سقف بازه تا گزارش روی دادهٔ بزرگ کند نشود */
    private const MAX_DAYS = 400;

    private function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly string $preset,
    ) {
    }

    public static function fromRequest(Request $request, string $default = '30d'): self
    {
        $preset = (string) $request->query('range', $default);
        if (!array_key_exists($preset, self::PRESETS)) {
            $preset = $default;
        }
        $today = Now::today();

        if ($preset === 'custom') {
            $from = jalali_date_from_request($request, 'from');
            $to = jalali_date_from_request($request, 'to');
            if ($from !== null && $to !== null) {
                $a = new DateTimeImmutable($from);
                $b = new DateTimeImmutable($to);
                if ($a > $b) {
                    [$a, $b] = [$b, $a];
                }
                if ((int) $a->diff($b)->days > self::MAX_DAYS) {
                    $a = $b->modify('-' . self::MAX_DAYS . ' days');
                }

                return new self($a, $b, 'custom');
            }
            $preset = $default;
        }

        [$jy, $jm] = Jalali::fromDateTime($today);

        return match ($preset) {
            'today' => new self($today, $today, $preset),
            '7d' => new self($today->modify('-6 days'), $today, $preset),
            '90d' => new self($today->modify('-89 days'), $today, $preset),
            'month' => new self(Jalali::toDateTime($jy, $jm, 1), $today, $preset),
            'last_month' => (static function () use ($jy, $jm): self {
                [$py, $pm] = $jm === 1 ? [$jy - 1, 12] : [$jy, $jm - 1];

                return new self(Jalali::toDateTime($py, $pm, 1), Jalali::toDateTime($py, $pm, Jalali::daysInJalaliMonth($py, $pm)), 'last_month');
            })(),
            'year' => new self(Jalali::toDateTime($jy, 1, 1), $today, $preset),
            default => new self($today->modify('-29 days'), $today, '30d'),
        };
    }

    public static function lastDays(int $days): self
    {
        $today = Now::today();

        return new self($today->modify('-' . ($days - 1) . ' days'), $today, $days . 'd');
    }

    /** بازهٔ هم‌اندازهٔ درست پیش از این. */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->modify('-' . $days . ' days'), $this->from->modify('-1 day'), 'previous');
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    public function start(): string
    {
        return $this->from->format('Y-m-d 00:00:00');
    }

    public function end(): string
    {
        return $this->to->format('Y-m-d 23:59:59');
    }

    public function label(): string
    {
        if ($this->from->format('Y-m-d') === $this->to->format('Y-m-d')) {
            return Jalali::format($this->from, 'j M Y');
        }

        return Jalali::format($this->from, 'j M Y') . ' تا ' . Jalali::format($this->to, 'j M Y');
    }

    /** @return array<string,string> پارامترهای نشانی برای پیوندهای همین بازه */
    public function query(): array
    {
        if ($this->preset !== 'custom') {
            return ['range' => $this->preset];
        }
        [$fy, $fm, $fd] = Jalali::fromDateTime($this->from);
        [$ty, $tm, $td] = Jalali::fromDateTime($this->to);

        return ['range' => 'custom', 'from_y' => (string) $fy, 'from_m' => (string) $fm, 'from_d' => (string) $fd, 'to_y' => (string) $ty, 'to_m' => (string) $tm, 'to_d' => (string) $td];
    }

    /** @return array<int,string> همهٔ روزهای بازه (Y-m-d) */
    public function dates(): array
    {
        $out = [];
        for ($d = $this->from; $d <= $this->to; $d = $d->modify('+1 day')) {
            $out[] = $d->format('Y-m-d');
        }

        return $out;
    }
}
