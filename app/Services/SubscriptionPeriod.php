<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SubscriptionPeriod
{
    public const MODE_FLOATING = 'floating';

    public const MODE_FIXED = 'fixed';

    public const MODES = [self::MODE_FLOATING, self::MODE_FIXED];

    public static function mode(): string
    {
        return Setting::get('subscription_mode', self::MODE_FLOATING);
    }

    public static function isRenewalApplicable(): bool
    {
        return self::mode() === self::MODE_FLOATING;
    }

    public static function fixedPeriodEnd(): string
    {
        return Setting::get('fixed_period_end', '31/07');
    }

    public static function fixedSwitchDate(): string
    {
        return Setting::get('fixed_switch_date', '01/06');
    }

    public static function computeEnd(string $subscriptionDate, ?CarbonInterface $currentEnd = null, bool $renewal = false): Carbon
    {
        return match (self::mode()) {
            self::MODE_FIXED => self::fixedEnd(Carbon::parse($subscriptionDate)),
            default => self::floatingEnd(Carbon::parse($subscriptionDate), $currentEnd, $renewal),
        };
    }

    private static function floatingEnd(Carbon $start, ?CarbonInterface $currentEnd, bool $renewal): Carbon
    {
        if ($renewal && $currentEnd instanceof CarbonInterface && $currentEnd->gt($start)) {
            $start = Carbon::instance($currentEnd->toDateTimeImmutable());
        }

        return $start->copy()->addYear();
    }

    private static function fixedEnd(Carbon $start): Carbon
    {
        [$switchMonth, $switchDay] = self::monthDay(self::fixedSwitchDate());
        [$endMonth, $endDay] = self::monthDay(self::fixedPeriodEnd());

        $switch = $start->copy()->setDate((int) $start->format('Y'), $switchMonth, $switchDay);
        $endYear = $start->lt($switch) ? (int) $start->format('Y') : (int) $start->format('Y') + 1;

        return Carbon::create($endYear, $endMonth, $endDay);
    }

    private static function monthDay(string $value): array
    {
        [$day, $month] = array_map('intval', explode('/', $value));

        return [$month, $day];
    }
}
