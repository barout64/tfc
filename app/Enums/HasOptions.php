<?php

namespace App\Enums;

trait HasOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function label(): string
    {
        return __(ucwords(str_replace('_', ' ', $this->value)));
    }
}
