<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Native\Mobile\Facades\SecureStorage as NativeSecureStorage;
use Throwable;

/**
 * "استخدام التخزين الآمن المتاح في NativePHP، أو abstraction مؤقت موثق إذا
 * لم تُثبّت الإضافة بعد" — nativephp/mobile v3.3.6 *is* installed and does
 * ship `Native\Mobile\SecureStorage` (native Keychain on iOS, EncryptedSharedPreferences/Keystore
 * on Android), so the primary path here is a straight delegation to it.
 *
 * The wrinkle: `Native\Mobile\SecureStorage` (and everything else in the
 * package) only does anything useful when a real device/bridge is on the
 * other end of `nativephp_call()` — that global function is *always*
 * defined once the package is autoloaded (it ships a Jump-hybrid-mode
 * fallback implementation), so checking `function_exists('nativephp_call')`
 * cannot tell "real bridge" from "no bridge" — only actually calling it
 * and looking at the result can. This class does exactly what
 * `Native\Mobile\SecureStorage` itself does internally: attempt the native
 * call, and treat a negative/`null` result — which is what happens with no
 * real device attached (e.g. `php artisan serve`/local web development,
 * which this Livewire app still supports, or this test suite) — as
 * license to fall back to Laravel's server-side session instead, with the
 * value **explicitly encrypted** via `Crypt` (keyed off `APP_KEY`) before
 * it's ever written there. Never plaintext, and never `localStorage`
 * ("عدم تخزين token في localStorage الويب" holds in both the native and
 * the fallback path, since neither ever touches browser storage at all).
 *
 * On a real device the native call always succeeds, so the fallback path
 * is simply never reached there — this is a genuine, documented,
 * *temporary* stand-in for the one case the real secure storage can't
 * reach, not a parallel storage location that could ever disagree with it.
 */
final class SecureStorage
{
    private const string SESSION_KEY_PREFIX = 'secure_storage.';

    public function get(string $key): ?string
    {
        $value = NativeSecureStorage::get($key);

        return $value ?? $this->getFromFallback($key);
    }

    public function set(string $key, ?string $value): bool
    {
        if (NativeSecureStorage::set($key, $value)) {
            return true;
        }

        return $this->setInFallback($key, $value);
    }

    public function delete(string $key): bool
    {
        NativeSecureStorage::delete($key);

        // Always also cleared, regardless of the native result above —
        // cheap, idempotent, and guarantees get() returns null afterwards
        // in every environment, not just whichever one actually held it.
        Session::forget(self::SESSION_KEY_PREFIX.$key);

        return true;
    }

    private function getFromFallback(string $key): ?string
    {
        $encrypted = Session::get(self::SESSION_KEY_PREFIX.$key);

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            // A corrupt/foreign-key ciphertext is treated as "not found",
            // never surfaced as an error — this is cache-like, best-effort
            // storage, not a source of truth.
            return null;
        }
    }

    private function setInFallback(string $key, ?string $value): bool
    {
        if ($value === null) {
            Session::forget(self::SESSION_KEY_PREFIX.$key);

            return true;
        }

        Session::put(self::SESSION_KEY_PREFIX.$key, Crypt::encryptString($value));

        return true;
    }
}
