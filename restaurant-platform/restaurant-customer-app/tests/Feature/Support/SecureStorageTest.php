<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\SecureStorage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * No native bridge is ever present in a test run — see this class's own
 * docblock — so every case here exercises the documented, temporary
 * encrypted-session fallback ("أو abstraction مؤقت موثق إذا لم تُثبّت
 * الإضافة بعد"). The real `Native\Mobile\SecureStorage` delegation is a
 * thin pass-through to an already-shipped package class, not this app's
 * own logic to test.
 */
class SecureStorageTest extends TestCase
{
    private function storage(): SecureStorage
    {
        return app(SecureStorage::class);
    }

    public function test_a_value_can_be_set_and_read_back(): void
    {
        $this->storage()->set('my-key', 'my-value');

        $this->assertSame('my-value', $this->storage()->get('my-key'));
    }

    public function test_an_unset_key_returns_null(): void
    {
        $this->assertNull($this->storage()->get('never-set'));
    }

    public function test_delete_removes_the_value(): void
    {
        $this->storage()->set('to-remove', 'value');
        $this->storage()->delete('to-remove');

        $this->assertNull($this->storage()->get('to-remove'));
    }

    public function test_the_value_is_encrypted_in_the_session_not_stored_in_plaintext(): void
    {
        $this->storage()->set('sensitive-key', 'a-real-secret-value');

        // Read the raw session value SecureStorage actually wrote,
        // bypassing its own get() so this test can't accidentally pass by
        // just calling the same decrypt path it's supposed to verify.
        $raw = Session::get('secure_storage.sensitive-key');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('a-real-secret-value', $raw);
        $this->assertSame('a-real-secret-value', Crypt::decryptString($raw));
    }

    public function test_setting_null_clears_the_value(): void
    {
        $this->storage()->set('a-key', 'a-value');
        $this->storage()->set('a-key', null);

        $this->assertNull($this->storage()->get('a-key'));
    }
}
