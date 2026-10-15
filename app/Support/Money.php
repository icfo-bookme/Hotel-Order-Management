<?php

namespace App\Support;

final class Money
{
    public static function cents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '0');
        $value = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return $negative ? -$value : $value;
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

}
