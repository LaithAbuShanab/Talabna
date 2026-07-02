<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\MenuCacheService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Active categories only, already ordered by sort_order. Not
     * paginated: this is a small, low-cardinality reference list (a
     * handful of categories per restaurant), unlike products.
     */
    public function index(MenuCacheService $cache): JsonResponse
    {
        return ApiResponse::success($cache->categoriesPayload());
    }
}
