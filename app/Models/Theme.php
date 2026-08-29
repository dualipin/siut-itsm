<?php

namespace App\Models;

use Database\Factories\ThemeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    /** @use HasFactory<ThemeFactory> */
    use HasFactory;

    protected $fillable = [
        'id', 'color_base_100', 'color_base_200', 'color_base_300',
        'color_base_content', 'color_primary', 'color_primary_content',
        'color_secondary', 'color_secondary_content', 'color_accent',
        'color_accent_content', 'color_neutral', 'color_neutral_content',
        'color_info', 'color_info_content', 'color_success',
        'color_success_content', 'color_warning', 'color_warning_content',
        'color_error', 'color_error_content', 'radius_box',
        'radius_selector', 'radius_field', 'size_field', 'size_selector',
        'border_width',
    ];

    protected $casts = [
        'id' => 'integer',
        'border_width' => 'integer',
    ];

    public function getVariants(?string $color): ?array
    {
        // Validar que el color exista y tenga un formato HEX válido
        if (! $color || ! preg_match('/^#?([a-fA-F0-9]{3}){1,2}$/', $color)) {
            return null;
        }

        // Posicionamos el color base en el 400 para sesgar la escala hacia la oscuridad.
        return [
            '50' => $this->mixColor($color, '#ffffff', 0.85), // 85% Blanco
            '100' => $this->mixColor($color, '#ffffff', 0.70), // 70% Blanco
            '200' => $this->mixColor($color, '#ffffff', 0.50), // 50% Blanco
            '300' => $this->mixColor($color, '#ffffff', 0.25), // 25% Blanco
            '400' => $color,                                   // Color Base
            '500' => $this->mixColor($color, '#000000', 0.15), // 15% Negro
            '600' => $this->mixColor($color, '#000000', 0.30), // 30% Negro
            '700' => $this->mixColor($color, '#000000', 0.45), // 45% Negro
            '800' => $this->mixColor($color, '#000000', 0.65), // 65% Negro
            '900' => $this->mixColor($color, '#000000', 0.80), // 80% Negro
            '950' => $this->mixColor($color, '#000000', 0.90), // 90% Negro
        ];
    }

    /**
     * Mezcla dos colores HEX basándose en un peso (weight).
     */
    private function mixColor(string $hex, string $mixWith, float $weight): string
    {
        // Normalizar HEX de 3 caracteres a 6 (ej. #FFF a #FFFFFF)
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $mixHex = ltrim($mixWith, '#');

        // Convertir el color base a RGB
        $r1 = hexdec(substr($hex, 0, 2));
        $g1 = hexdec(substr($hex, 2, 2));
        $b1 = hexdec(substr($hex, 4, 2));

        // Convertir el color de mezcla (Blanco o Negro) a RGB
        $r2 = hexdec(substr($mixHex, 0, 2));
        $g2 = hexdec(substr($mixHex, 2, 2));
        $b2 = hexdec(substr($mixHex, 4, 2));

        // Calcular la mezcla según el porcentaje ($weight)
        $r = (int) round($r1 * (1 - $weight) + $r2 * $weight);
        $g = (int) round($g1 * (1 - $weight) + $g2 * $weight);
        $b = (int) round($b1 * (1 - $weight) + $b2 * $weight);

        // Devolver el nuevo color en formato HEX
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
