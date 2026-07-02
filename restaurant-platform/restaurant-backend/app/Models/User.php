<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Notifications\ApiResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Only administrative roles (anything but "customer" — see
     * UserRole::isAdmin()) may log into the Filament admin panel, and only
     * while still active — the same users table also holds ordinary
     * customers (see docs/DATABASE_SCHEMA.md), so without this check any
     * customer could otherwise sign into /admin, and a deactivated staff
     * account could keep logging in indefinitely.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->isAdmin() && $this->is_active;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Override the default web-URL reset email with an API-appropriate one
     * that sends the raw token, since this backend has no reset-password
     * web page for Laravel's default notification to link to.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ApiResetPasswordNotification($token));
    }
}
