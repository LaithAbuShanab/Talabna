<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeliveryZone\DeliveryZoneCheckRequest;
use App\Http\Resources\Menu\DeliveryZoneResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryZone;
use App\Services\MenuCacheService;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class DeliveryZoneController extends Controller
{
    public function index(MenuCacheService $cache): JsonResponse
    {
        return ApiResponse::success($cache->deliveryZonesPayload());
    }

    /**
     * A preliminary "can we deliver here" check — either against a zone
     * the client already picked (`zone_id`) or a raw coordinate
     * (`latitude`/`longitude`), matched against each active zone's
     * center point + radius (see App\Models\DeliveryZone). This is
     * informational only: the authoritative check still happens inside
     * App\Services\CartPricingService when an order is actually priced.
     */
    public function check(DeliveryZoneCheckRequest $request, MenuCacheService $cache): JsonResponse
    {
        $zones = $cache->deliveryZones();

        $zone = $request->filled('zone_id')
            ? $zones->firstWhere('id', $request->integer('zone_id'))
            : $this->findZoneByCoordinates($zones, (float) $request->input('latitude'), (float) $request->input('longitude'));

        return ApiResponse::success([
            'deliverable' => $zone !== null,
            'zone' => $zone !== null ? new DeliveryZoneResource($zone) : null,
        ]);
    }

    /**
     * @param  Collection<int, DeliveryZone>  $zones
     */
    private function findZoneByCoordinates($zones, float $latitude, float $longitude): ?DeliveryZone
    {
        return $zones->first(function (DeliveryZone $zone) use ($latitude, $longitude): bool {
            if ($zone->latitude === null || $zone->longitude === null || $zone->radius_meters === null) {
                return false;
            }

            $distance = Geo::distanceMeters($latitude, $longitude, (float) $zone->latitude, (float) $zone->longitude);

            return $distance <= $zone->radius_meters;
        });
    }
}
