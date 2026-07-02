<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\ProductIndexRequest;
use App\Http\Responses\ApiResponse;
use App\Services\MenuCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    private const int DEFAULT_PER_PAGE = 20;

    /**
     * Active products belonging to an active category, filtered by
     * `category_id` and/or `search` (case-insensitive substring match
     * against both the English and Arabic name — this doubles as the
     * "search products" endpoint, rather than a separate near-duplicate
     * route), then paginated. Reads from MenuCacheService::products()'s
     * cached, already-resource-shaped `list` array and filters/paginates
     * it in memory instead of querying per filter combination.
     */
    public function index(ProductIndexRequest $request, MenuCacheService $cache): JsonResponse
    {
        $products = Collection::make($cache->products()['list']);

        if ($request->filled('category_id')) {
            $categoryId = $request->integer('category_id');
            $products = $products->where('category_id', $categoryId);
        }

        $search = trim((string) $request->string('search'));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $products = $products->filter(
                fn (array $product) => str_contains(mb_strtolower($product['name']['en']), $needle)
                    || str_contains(mb_strtolower($product['name']['ar']), $needle)
            );
        }

        $products = $products->values();

        $perPage = $request->filled('per_page') ? $request->integer('per_page') : self::DEFAULT_PER_PAGE;
        $page = $request->filled('page') ? $request->integer('page') : 1;
        $paged = $products->forPage($page, $perPage)->values();

        return ApiResponse::success([
            'data' => $paged,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $products->count(),
                'last_page' => (int) max(1, ceil($products->count() / $perPage)),
            ],
        ]);
    }

    public function show(int $product, MenuCacheService $cache): JsonResponse
    {
        $found = $cache->products()['detail'][$product] ?? null;

        abort_if($found === null, 404, 'The requested resource was not found.');

        return ApiResponse::success($found);
    }
}
