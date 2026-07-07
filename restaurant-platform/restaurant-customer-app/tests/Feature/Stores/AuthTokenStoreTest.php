<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Stores\AuthTokenStore;
use Tests\TestCase;

class AuthTokenStoreTest extends TestCase
{
    public function test_there_is_no_token_initially(): void
    {
        $store = app(AuthTokenStore::class);

        $this->assertNull($store->token());
        $this->assertFalse($store->hasToken());
    }

    public function test_a_token_can_be_stored_and_read_back(): void
    {
        $store = app(AuthTokenStore::class);

        $store->put('a-sanctum-token');

        $this->assertSame('a-sanctum-token', $store->token());
        $this->assertTrue($store->hasToken());
    }

    public function test_forget_removes_the_token(): void
    {
        $store = app(AuthTokenStore::class);
        $store->put('a-sanctum-token');

        $store->forget();

        $this->assertNull($store->token());
        $this->assertFalse($store->hasToken());
    }
}
