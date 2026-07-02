<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\Menu\CategoryResource;
use App\Http\Resources\Menu\DeliveryZoneResource;
use App\Http\Resources\Menu\ProductDetailResource;
use App\Http\Resources\Menu\ProductListResource;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\RestaurantSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-through cache for the public menu API (docs/API_MENU.md). All of
 * this data changes rarely (a menu edit, not a per-request event), so
 * every read goes through Cache::remember() with a 1 hour TTL, and every
 * write path (Filament, tinker, seeders — anything using these Eloquent
 * models) is invalidated by App\Observers\MenuCacheObserver, registered in
 * AppServiceProvider::boot(). Plain string keys, not Cache::tags(), since
 * the configured cache store (`database`, see config/cache.php) doesn't
 * support tagging.
 *
 * Every cached value here is a **plain array**, never an Eloquent model or
 * Collection. The `database` cache store's `serializable_classes` config
 * defaults to `false` (a deliberate Laravel security hardening against
 * PHP-object-injection/gadget-chain attacks) — unserializing a cached
 * object then silently returns a useless `__PHP_Incomplete_Class`. Rather
 * than weaken that security setting, models are cached via `->toArray()`
 * and reconstituted with `Model::hydrate()`/`newFromBuilder()` (flat
 * models: categories, delivery zones, business hours, restaurant
 * settings), or — for products, whose relation tree is too deep to
 * reconstitute cleanly — the *already-resolved API Resource arrays* are
 * cached directly (`products()`), so Resource-shaping only runs once per
 * cache population, not per request.
 */
final class MenuCacheService
{
    public const string CATEGORIES_KEY = 'menu:categories';

    public const string PRODUCTS_KEY = 'menu:products:active';

    public const string DELIVERY_ZONES_KEY = 'menu:delivery-zones';

    public const string RESTAURANT_SETTINGS_KEY = 'menu:restaurant-settings';

    public const string BUSINESS_HOURS_KEY = 'menu:business-hours';

    private const int TTL_SECONDS = 3600;

    /**
     * @return Collection<int, Category>
     */
    public function categories()
    {
        $rows = Cache::remember(self::CATEGORIES_KEY, self::TTL_SECONDS, fn () => Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray());

        return Category::hydrate($rows);
    }

    /**
     * @return array{list: list<array<string, mixed>>, detail: array<int, array<string, mixed>>}
     */
    public function products(): array
    {
        return Cache::remember(self::PRODUCTS_KEY, self::TTL_SECONDS, function (): array {
            $products = Product::query()
                ->where('is_active', true)
                ->where('is_available', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->with([
                    'category',
                    'images' => fn ($query) => $query->orderBy('sort_order'),
                    'optionGroups' => fn ($query) => $query->orderBy('product_option_groups.sort_order'),
                    'optionGroups.values' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                ])
                ->orderBy('sort_order')
                ->get();

            return [
                'list' => self::deepResolve(ProductListResource::collection($products)),
                'detail' => $products->mapWithKeys(
                    fn (Product $product) => [$product->id => self::deepResolve(new ProductDetailResource($product))]
                )->all(),
            ];
        });
    }

    /**
     * @return Collection<int, DeliveryZone>
     */
    public function deliveryZones()
    {
        $rows = Cache::remember(self::DELIVERY_ZONES_KEY, self::TTL_SECONDS, fn () => DeliveryZone::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray());

        return DeliveryZone::hydrate($rows);
    }

    public function restaurantSettings(): RestaurantSetting
    {
        $row = Cache::remember(self::RESTAURANT_SETTINGS_KEY, self::TTL_SECONDS, fn () => RestaurantSetting::current()->toArray());

        return (new RestaurantSetting)->newFromBuilder($row);
    }

    /**
     * @return Collection<int, BusinessHour>
     */
    public function businessHours()
    {
        $rows = Cache::remember(self::BUSINESS_HOURS_KEY, self::TTL_SECONDS, fn () => BusinessHour::query()
            ->orderBy('day_of_week')
            ->get()
            ->toArray());

        return BusinessHour::hydrate($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categoriesPayload(): array
    {
        return self::deepResolve(CategoryResource::collection($this->categories()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deliveryZonesPayload(): array
    {
        return self::deepResolve(DeliveryZoneResource::collection($this->deliveryZones()));
    }

    /**
     * `JsonResource::resolve()` only converts the *outer* resource to an
     * array — any nested resource returned as a value inside that array
     * (e.g. ProductDetailResource's `category` or `option_groups`) is left
     * as a live Resource object, not a plain array. That's normally
     * invisible because Laravel's real HTTP response cycle runs the whole
     * structure through `json_encode()`, which recurses into every
     * JsonSerializable value it finds, nested or not. Caching `resolve()`'s
     * output directly skips that recursion and would cache half-resolved
     * Resource objects — which then fail to unserialize on a cache hit
     * (see this class's docblock). Forcing the same json_encode() ->
     * json_decode() round trip here guarantees a fully plain, cacheable
     * array no matter how deeply resources are nested.
     *
     * @return array<mixed>
     */
    private static function deepResolve(mixed $resource): array
    {
        return json_decode(json_encode($resource, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    public function forgetCategoriesAndProducts(): void
    {
        Cache::forget(self::CATEGORIES_KEY);
        Cache::forget(self::PRODUCTS_KEY);
    }

    public function forgetDeliveryZones(): void
    {
        Cache::forget(self::DELIVERY_ZONES_KEY);
    }

    public function forgetRestaurantSettings(): void
    {
        Cache::forget(self::RESTAURANT_SETTINGS_KEY);
    }

    public function forgetBusinessHours(): void
    {
        Cache::forget(self::BUSINESS_HOURS_KEY);
    }
}
