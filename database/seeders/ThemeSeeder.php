<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Theme::updateOrInsert(
            ['id' => 1],
            [
                'color_base_100' => '#ffffff',
                'color_base_200' => '#f8f9fa',
                'color_base_300' => '#e9ecef',
                'color_base_content' => '#212529',
                'color_primary' => '#611232',
                'color_primary_content' => '#ffffff',
                'color_secondary' => '#a57f2c',
                'color_secondary_content' => '#ffffff',
                'color_accent' => '#a57f2c',
                'color_accent_content' => '#ffffff',
                'color_neutral' => '#212529',
                'color_neutral_content' => '#ffffff',
                'color_info' => '#17a2b8',
                'color_info_content' => '#ffffff',
                'color_success' => '#38b44a',
                'color_success_content' => '#ffffff',
                'color_warning' => '#efb73e',
                'color_warning_content' => '#212529',
                'color_error' => '#df382c',
                'color_error_content' => '#ffffff',
                'radius_selector' => '1',
                'radius_field' => '1',
                'radius_box' => '1',
                'size_field' => '0.25',
                'size_selector' => '0.25',
                'border_width' => '1',
            ]
        );
    }
}
