<?php

namespace App\Enums;

enum TransparencyAiSourceKind: string
{
    case None = 'none';
    case Text = 'text';
    case Image = 'image';
}
