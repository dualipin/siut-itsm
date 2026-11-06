<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class EfirmaSigner extends Field
{
    protected string $view = 'filament.forms.components.efirma-signer';

    protected ?string $expectedCurp = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Firma Electrónica Avanzada (e.firma del SAT)');
        $this->helperText('Seleccione sus archivos .cer, .key e ingrese la contraseña de su clave privada. La llave y contraseña nunca se transfieren al servidor.');
    }

    public function expectedCurp(?string $curp): static
    {
        $this->expectedCurp = $curp;

        return $this;
    }

    public function getExpectedCurp(): ?string
    {
        return $this->expectedCurp ?? auth()->user()?->curp;
    }
}
