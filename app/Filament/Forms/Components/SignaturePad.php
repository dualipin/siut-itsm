<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class SignaturePad extends Field
{
    protected string $view = 'filament.forms.components.signature-pad';

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Firma del Solicitante');
        $this->helperText('Dibuje su firma dentro del recuadro usando su ratón, lápiz táctil o dedo.');
        $this->rules(['required', 'string', 'starts_with:data:image/']);
        $this->validationMessages([
            'required' => 'Debes plasmar tu firma en el recuadro para poder enviar la solicitud.',
            'starts_with' => 'El formato del trazo de firma no es válido.',
        ]);
    }
}
