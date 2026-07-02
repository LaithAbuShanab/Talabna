<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BusinessHour;
use App\Models\DeliveryZone;
use App\Models\RestaurantSetting;
use App\Services\MenuCacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates App\Services\MenuCacheService's cached reads whenever any
 * menu-related model changes — registered in AppServiceProvider::boot()
 * for Category, Product, OptionGroup, OptionValue, ProductOptionGroup,
 * DeliveryZone, RestaurantSetting, and BusinessHour. Model events fire
 * regardless of what triggered the save/delete (Filament, tinker, a
 * seeder, a future admin API), so this correctly invalidates the cache
 * when Filament Resources for these models are added later without any
 * further changes here.
 *
 * Known gap: attaching/detaching a product's option groups via
 * BelongsToMany::sync()/attach()/detach() (how a Filament relation manager
 * would typically manage App\Models\ProductOptionGroup) does not reliably
 * fire Eloquent model events on the pivot model — only explicit
 * Pivot::save()/delete() calls do. The 1 hour cache TTL bounds how long
 * that specific edge case could stay stale.
 */
final class MenuCacheObserver
{
    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    public function deleted(Model $model): void
    {
        $this->forget($model);
    }

    public function restored(Model $model): void
    {
        $this->forget($model);
    }

    private function forget(Model $model): void
    {
        $service = app(MenuCacheService::class);

        match (true) {
            $model instanceof RestaurantSetting => $service->forgetRestaurantSettings(),
            $model instanceof BusinessHour => $service->forgetBusinessHours(),
            $model instanceof DeliveryZone => $service->forgetDeliveryZones(),
            default => $service->forgetCategoriesAndProducts(),
        };
    }
}
