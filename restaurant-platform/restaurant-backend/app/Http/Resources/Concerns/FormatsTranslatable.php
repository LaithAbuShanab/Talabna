<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

/**
 * Bilingual menu content is stored as two plain columns per field — `name`
 * (English, also the pre-existing column every other part of the codebase
 * already reads as a string) and `name_ar` (nullable) — rather than a JSON
 * translations column, specifically so nothing outside the public menu API
 * had to change (App\Services\CartPricingService, App\Actions\CreateOrderAction,
 * and the order snapshot columns all keep reading `name` as a plain
 * string). See docs/API_MENU.md's "Bilingual content" section.
 *
 * The API always returns both languages as `{en, ar}`, falling back to
 * English when no Arabic translation has been entered yet, so a field is
 * never null.
 */
trait FormatsTranslatable
{
    /**
     * @return array{en: string, ar: string}
     */
    protected function translatable(?string $en, ?string $ar): array
    {
        $en ??= '';

        return [
            'en' => $en,
            'ar' => $ar ?? $en,
        ];
    }
}
