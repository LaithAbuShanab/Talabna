<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateOrderAction;
use App\DataTransferObjects\Cart\CartItemInputData;
use App\DataTransferObjects\Cart\CartPricingRequestData;
use App\DataTransferObjects\Order\CreateOrderData;
use App\DataTransferObjects\Order\TransitionOrderStatusData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\CancelOrderRequest;
use App\Http\Requests\Api\V1\Order\CreateOrderRequest;
use App\Http\Requests\Api\V1\Order\OrderIndexRequest;
use App\Http\Requests\Api\V1\Order\ReorderPreviewRequest;
use App\Http\Resources\Cart\CartPreviewResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Services\CartPricingService;
use App\Services\OrderStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    private const int DEFAULT_PER_PAGE = 20;

    public function __construct(
        private readonly CreateOrderAction $createOrderAction,
        private readonly OrderStatusTransitionService $transitionService,
        private readonly CartPricingService $pricingService,
    ) {}

    /**
     * The authenticated user's own orders only, newest first, optionally
     * filtered by `status`. Deliberately lean — no items/status
     * history/payments loaded — so the list payload stays small; the full
     * breakdown is what `show()` is for.
     */
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $perPage = $request->filled('per_page') ? $request->integer('per_page') : self::DEFAULT_PER_PAGE;

        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('coupon')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->value())
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::success([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Every business rule (restaurant open, cart/coupon/option validity,
     * delivery address ownership) is enforced inside CreateOrderAction —
     * this controller only translates the request into CreateOrderData.
     * Idempotent: the same (user, Idempotency-Key) pair always returns the
     * same order rather than creating a second one — see CreateOrderRequest.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $items = collect($data['items'])
            ->map(fn (array $item) => new CartItemInputData(
                productId: (int) $item['product_id'],
                quantity: (int) $item['quantity'],
                optionValueIds: array_map('intval', $item['option_value_ids'] ?? []),
            ))
            ->all();

        $order = $this->createOrderAction->execute(new CreateOrderData(
            userId: $request->user()->id,
            items: $items,
            deliveryType: DeliveryType::from($data['delivery_type']),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            idempotencyKey: $data['idempotency_key'],
            deliveryZoneId: $data['delivery_zone_id'] ?? null,
            customerAddressId: $data['customer_address_id'] ?? null,
            couponCode: $data['coupon_code'] ?? null,
            customerNotes: $data['customer_notes'] ?? null,
        ));

        return ApiResponse::success(new OrderResource($order), '', 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->load(['coupon', 'items.options', 'statusHistories', 'payments', 'review']);

        return ApiResponse::success(new OrderResource($order));
    }

    /**
     * Just the status timeline, for a client that only wants to poll
     * order progress without the full order payload.
     */
    public function timeline(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $histories = $order->statusHistories()->oldest('created_at')->get();

        return ApiResponse::success(OrderStatusHistoryResource::collection($histories));
    }

    /**
     * Ownership is checked by CancelOrderRequest::authorize(); whether
     * cancellation is actually allowed at the order's current status is
     * OrderStatusTransitionService's job (via OrderPolicy::cancelAsCustomer()).
     */
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $updated = $this->transitionService->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Cancelled,
            actor: $request->user(),
            reason: $request->validated('reason'),
        ));

        return ApiResponse::success(new OrderResource($updated));
    }

    /**
     * Recomputes a past order's items at *current* prices/availability via
     * CartPricingService and returns the result as a cart proposal — no
     * new Order is ever created here. Items whose product was deleted
     * since are silently dropped (listed in `unavailable_items`); any
     * other pricing failure (now unavailable, option no longer valid, etc.)
     * surfaces the same CartPricingException a POST /cart/preview call
     * would, since this ultimately calls the exact same service.
     */
    public function reorderPreview(ReorderPreviewRequest $request, Order $order): JsonResponse
    {
        $order->load('items.options');

        $items = $order->items
            ->filter(fn ($item) => $item->product_id !== null)
            ->map(fn ($item) => new CartItemInputData(
                productId: $item->product_id,
                quantity: $item->quantity,
                optionValueIds: $item->options->pluck('option_value_id')->filter()->values()->all(),
            ))
            ->values()
            ->all();

        $unavailableItems = $order->items
            ->filter(fn ($item) => $item->product_id === null)
            ->pluck('product_name')
            ->values()
            ->all();

        $result = $this->pricingService->price(new CartPricingRequestData(
            items: $items,
            deliveryType: $order->delivery_type,
            deliveryZoneId: $order->delivery_zone_id,
            couponCode: $request->validated('coupon_code'),
            userId: $order->user_id,
        ));

        return ApiResponse::success([
            'source_order' => ['id' => $order->id, 'order_number' => $order->order_number],
            'preview' => new CartPreviewResource($result),
            'unavailable_items' => $unavailableItems,
        ]);
    }
}
