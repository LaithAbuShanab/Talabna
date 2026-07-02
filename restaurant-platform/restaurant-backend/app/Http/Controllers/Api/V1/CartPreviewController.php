<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\Cart\CartItemInputData;
use App\DataTransferObjects\Cart\CartPricingRequestData;
use App\Enums\DeliveryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cart\CartPreviewRequest;
use App\Http\Resources\Cart\CartPreviewResource;
use App\Http\Responses\ApiResponse;
use App\Services\CartPricingService;
use Illuminate\Http\JsonResponse;

class CartPreviewController extends Controller
{
    public function __construct(private readonly CartPricingService $pricingService) {}

    /**
     * Public — no authentication required to preview a cart. If the
     * request happens to carry a valid bearer token, the user is resolved
     * anyway (without the `auth:sanctum` middleware forcing one) so a
     * coupon's per-user usage limit can still be checked; an anonymous
     * preview simply skips that one check, exactly like
     * App\Services\CartPricingService already behaves when userId is null.
     */
    public function preview(CartPreviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        $items = collect($data['items'])
            ->map(fn (array $item) => new CartItemInputData(
                productId: (int) $item['product_id'],
                quantity: (int) $item['quantity'],
                optionValueIds: array_map('intval', $item['option_value_ids'] ?? []),
            ))
            ->all();

        $result = $this->pricingService->price(new CartPricingRequestData(
            items: $items,
            deliveryType: DeliveryType::from($data['delivery_type']),
            deliveryZoneId: $data['delivery_zone_id'] ?? null,
            couponCode: $data['coupon_code'] ?? null,
            userId: $request->user('sanctum')?->id,
        ));

        return ApiResponse::success(new CartPreviewResource($result));
    }
}
