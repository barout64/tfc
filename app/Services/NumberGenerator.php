<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NumberGenerator
{
    /**
     * Sequential human-readable number, e.g. DSP-2026-000123.
     *
     * @param  class-string<Model>  $model
     */
    public static function next(string $model, string $column, string $prefix): string
    {
        $year = now()->format('Y');
        $base = "{$prefix}-{$year}-";

        $last = $model::query()->where($column, 'like', $base.'%')->orderByDesc($column)->value($column);
        $sequence = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $base.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Short code that is easy to read out over the phone (no 0/O/1/I).
     */
    public static function code(int $length = 8): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
