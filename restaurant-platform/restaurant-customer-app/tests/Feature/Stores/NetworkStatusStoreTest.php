<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Stores\NetworkStatusStore;
use Native\Mobile\Network as NativeNetwork;
use Tests\TestCase;

class NetworkStatusStoreTest extends TestCase
{
    public function test_it_assumes_online_when_no_native_bridge_is_present(): void
    {
        // The default in any test/plain-browser context — see this
        // class's own docblock.
        $this->assertTrue(app(NetworkStatusStore::class)->isOnline());
    }

    public function test_it_reports_offline_when_the_native_bridge_says_so(): void
    {
        $this->app->bind(NativeNetwork::class, fn () => new class
        {
            public function status(): ?object
            {
                return (object) ['connected' => false];
            }
        });

        $this->assertFalse(app(NetworkStatusStore::class)->isOnline());
    }

    public function test_it_reports_online_when_the_native_bridge_says_so(): void
    {
        $this->app->bind(NativeNetwork::class, fn () => new class
        {
            public function status(): ?object
            {
                return (object) ['connected' => true];
            }
        });

        $this->assertTrue(app(NetworkStatusStore::class)->isOnline());
    }

    public function test_a_status_object_missing_the_connected_property_assumes_online(): void
    {
        $this->app->bind(NativeNetwork::class, fn () => new class
        {
            public function status(): ?object
            {
                return (object) ['type' => 'wifi'];
            }
        });

        $this->assertTrue(app(NetworkStatusStore::class)->isOnline());
    }
}
