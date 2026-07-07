<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\DeviceNameResolver;
use Native\Mobile\Device as NativeDevice;
use Tests\TestCase;

class DeviceNameResolverTest extends TestCase
{
    public function test_it_returns_web_browser_when_no_native_bridge_is_present(): void
    {
        // The default in any test/plain-browser context.
        $this->assertSame('Web Browser', app(DeviceNameResolver::class)->resolve());
    }

    public function test_it_uses_the_model_field_when_present(): void
    {
        $this->app->bind(NativeDevice::class, fn () => new class extends NativeDevice
        {
            public function getInfo(): ?string
            {
                return json_encode(['platform' => 'ios', 'model' => 'iPhone 15 Pro']);
            }
        });

        $this->assertSame('iPhone 15 Pro', app(DeviceNameResolver::class)->resolve());
    }

    public function test_it_falls_back_to_platform_when_no_model_is_present(): void
    {
        $this->app->bind(NativeDevice::class, fn () => new class extends NativeDevice
        {
            public function getInfo(): ?string
            {
                return json_encode(['platform' => 'android']);
            }
        });

        $this->assertSame('Android Device', app(DeviceNameResolver::class)->resolve());
    }

    public function test_it_falls_back_to_a_generic_name_for_unrecognized_json(): void
    {
        $this->app->bind(NativeDevice::class, fn () => new class extends NativeDevice
        {
            public function getInfo(): ?string
            {
                return 'not-json-at-all';
            }
        });

        $this->assertSame('Mobile Device', app(DeviceNameResolver::class)->resolve());
    }
}
