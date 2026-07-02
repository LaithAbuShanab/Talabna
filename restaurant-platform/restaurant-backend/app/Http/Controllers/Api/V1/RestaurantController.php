<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Menu\BusinessHourResource;
use App\Http\Resources\Menu\RestaurantSettingResource;
use App\Http\Responses\ApiResponse;
use App\Services\MenuCacheService;
use App\Services\RestaurantAvailabilityService;
use Illuminate\Http\JsonResponse;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly MenuCacheService $cache,
        private readonly RestaurantAvailabilityService $availability,
    ) {}

    public function info(): JsonResponse
    {
        return ApiResponse::success(new RestaurantSettingResource($this->cache->restaurantSettings()));
    }

    public function hours(): JsonResponse
    {
        return ApiResponse::success(BusinessHourResource::collection($this->cache->businessHours()));
    }

    /**
     * Whether the restaurant is open right now. Deliberately not cached
     * like the rest of App\Services\MenuCacheService: this depends on the
     * current wall-clock time, so it's computed fresh on every request
     * (the settings/business-hours rows it reads are still served from
     * cache).
     */
    public function isOpen(): JsonResponse
    {
        return ApiResponse::success([
            'is_open' => $this->availability->isOpenNow($this->cache->restaurantSettings()),
        ]);
    }
}
