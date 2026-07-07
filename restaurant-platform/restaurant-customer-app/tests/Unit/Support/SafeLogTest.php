<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SafeLog;
use PHPUnit\Framework\TestCase;

/**
 * "logging آمن دون tokens أو كلمات مرور" — pure logic, no framework boot
 * needed, so this stays a true Unit test per docs/TESTING.md's own rule
 * (mirrored from restaurant-backend's conventions).
 */
class SafeLogTest extends TestCase
{
    public function test_it_redacts_known_sensitive_keys(): void
    {
        $result = SafeLog::redact([
            'email' => 'jane@example.com',
            'password' => 'super-secret',
            'token' => 'abc123',
            'api_key' => 'xyz',
        ]);

        $this->assertSame('jane@example.com', $result['email']);
        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('[REDACTED]', $result['token']);
        $this->assertSame('[REDACTED]', $result['api_key']);
    }

    public function test_it_redacts_keys_ending_in_a_sensitive_suffix(): void
    {
        $result = SafeLog::redact([
            'access_token' => 'abc',
            'refresh_token' => 'def',
            'current_password' => 'ghi',
            'client_secret' => 'jkl',
            'encryption_key' => 'mno',
        ]);

        foreach ($result as $value) {
            $this->assertSame('[REDACTED]', $value);
        }
    }

    public function test_it_is_case_insensitive(): void
    {
        $result = SafeLog::redact(['PASSWORD' => 'secret', 'Token' => 'abc']);

        $this->assertSame('[REDACTED]', $result['PASSWORD']);
        $this->assertSame('[REDACTED]', $result['Token']);
    }

    public function test_it_redacts_nested_arrays_too(): void
    {
        $result = SafeLog::redact([
            'user' => [
                'name' => 'Jane',
                'password' => 'secret',
            ],
        ]);

        $this->assertSame('Jane', $result['user']['name']);
        $this->assertSame('[REDACTED]', $result['user']['password']);
    }

    public function test_it_never_touches_non_sensitive_keys(): void
    {
        $result = SafeLog::redact(['order_id' => 123, 'status' => 'pending']);

        $this->assertSame(123, $result['order_id']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_it_redacts_authorization_and_cookie_headers(): void
    {
        $result = SafeLog::redactHeaders([
            'Authorization' => 'Bearer abc123',
            'Cookie' => 'session=xyz',
            'Accept' => 'application/json',
        ]);

        $this->assertSame('[REDACTED]', $result['Authorization']);
        $this->assertSame('[REDACTED]', $result['Cookie']);
        $this->assertSame('application/json', $result['Accept']);
    }
}
