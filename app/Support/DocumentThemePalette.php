<?php

namespace App\Support;

use App\Models\Theme;

class DocumentThemePalette
{
    /**
     * @return array{
     *     primary: string,
     *     primary_content: string,
     *     secondary: string,
     *     secondary_content: string,
     *     accent: string,
     *     accent_content: string,
     *     base_100: string,
     *     base_200: string,
     *     base_300: string,
     *     base_content: string,
     *     warning: string,
     *     warning_content: string,
     *     info: string
     * }
     */
    public function resolve(?Theme $theme = null): array
    {
        $theme ??= Theme::query()->whereKey(1)->first() ?? Theme::query()->first();

        $fallbacks = [
            'primary' => '#611232',
            'primary_content' => '#ffffff',
            'secondary' => '#a57f2c',
            'secondary_content' => '#ffffff',
            'accent' => '#a57f2c',
            'accent_content' => '#ffffff',
            'base_100' => '#f9fafb',
            'base_200' => '#f2f2f2',
            'base_300' => '#dddddd',
            'base_content' => '#000000',
            'warning' => '#f59e0b',
            'warning_content' => '#92400e',
            'info' => '#eff6ff',
        ];

        $colors = [];

        foreach ($fallbacks as $key => $fallback) {
            $colors[$key] = $this->validHex($theme?->{$this->themeAttribute($key)}, $fallback);
        }

        return $colors;
    }

    private function themeAttribute(string $key): string
    {
        return 'color_'.$key;
    }

    private function validHex(?string $value, string $fallback): string
    {
        if ($value !== null && preg_match('/^#?(?:[a-fA-F0-9]{3}){1,2}$/', $value) === 1) {
            return str_starts_with($value, '#') ? $value : '#'.$value;
        }

        return $fallback;
    }
}
