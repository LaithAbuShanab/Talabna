<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Models\BusinessHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessHour
 */
final class BusinessHourResource extends JsonResource
{
    private const array DAY_NAMES = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week,
            'day_name' => self::DAY_NAMES[$this->day_of_week] ?? null,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'is_closed' => $this->is_closed,
        ];
    }
}
