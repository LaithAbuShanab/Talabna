<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Address\StoreCustomerAddressRequest;
use App\Http\Requests\Api\V1\Address\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->latest()->get();

        return ApiResponse::success(CustomerAddressResource::collection($addresses));
    }

    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $address = DB::transaction(function () use ($user, $data): CustomerAddress {
            if (($data['is_default'] ?? false) || ! $user->addresses()->exists()) {
                $user->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            return $user->addresses()->create($data);
        });

        return ApiResponse::success(new CustomerAddressResource($address), '', 201);
    }

    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($address, $data): void {
            if ($data['is_default'] ?? false) {
                $address->user->addresses()->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return ApiResponse::success(new CustomerAddressResource($address->fresh()));
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return ApiResponse::success(null, trans('address.deleted'));
    }

    public function setDefault(Request $request, CustomerAddress $address): JsonResponse
    {
        Gate::authorize('update', $address);

        DB::transaction(function () use ($address): void {
            $address->user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return ApiResponse::success(new CustomerAddressResource($address->fresh()), trans('address.default_set'));
    }
}
