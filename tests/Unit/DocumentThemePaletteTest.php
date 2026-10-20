<?php

use App\Models\Theme;
use App\Support\DocumentThemePalette;

it('resolves valid theme colors and normalizes hex values', function () {
    $theme = new Theme([
        'color_primary' => '123456',
        'color_primary_content' => '#abcdef',
        'color_base_100' => '#101010',
        'color_base_200' => '#202020',
        'color_base_300' => '#303030',
        'color_base_content' => '#404040',
        'color_secondary' => '#505050',
        'color_secondary_content' => '#ffffff',
        'color_accent' => '#606060',
        'color_accent_content' => '#ffffff',
        'color_warning' => '#707070',
        'color_warning_content' => '#ffffff',
    ]);

    $colors = (new DocumentThemePalette)->resolve($theme);

    expect($colors['primary'])->toBe('#123456')
        ->and($colors['primary_content'])->toBe('#abcdef')
        ->and($colors['base_200'])->toBe('#202020')
        ->and($colors['warning'])->toBe('#707070');
});

it('uses safe fallbacks for missing or invalid theme colors', function () {
    $theme = new Theme([
        'color_primary' => 'not-a-color',
        'color_primary_content' => null,
    ]);

    $colors = (new DocumentThemePalette)->resolve($theme);

    expect($colors['primary'])->toBe('#611232')
        ->and($colors['primary_content'])->toBe('#ffffff')
        ->and($colors['base_100'])->toBe('#f9fafb')
        ->and($colors['base_300'])->toBe('#dddddd');
});
