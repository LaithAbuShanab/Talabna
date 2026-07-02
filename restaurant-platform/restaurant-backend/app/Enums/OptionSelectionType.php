<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OptionSelectionType: string implements HasLabel
{
    case Single = 'single';
    case Multiple = 'multiple';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Single choice',
            self::Multiple => 'Multiple choice',
        };
    }
}
