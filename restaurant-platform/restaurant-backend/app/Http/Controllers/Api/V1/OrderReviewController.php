<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Exceptions\OrderReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\SubmitOrderReviewRequest;
use App\Http\Resources\OrderReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderReviewController extends Controller
{
    /**
     * Ownership is checked by SubmitOrderReviewRequest::authorize(); the
     * order must be delivered and not already reviewed (order_reviews.order_id
     * is also unique at the database level as a second line of defense
     * against a race between two concurrent submissions).
     */
    public function store(SubmitOrderReviewRequest $request, Order $order): JsonResponse
    {
        if ($order->status !== OrderStatus::Delivered) {
            throw new OrderReviewException('order_not_delivered');
        }

        if ($order->review()->exists()) {
            throw new OrderReviewException('already_reviewed');
        }

        $review = $order->review()->create([
            'user_id' => $request->user()->id,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        return ApiResponse::success(new OrderReviewResource($review), '', 201);
    }
}
