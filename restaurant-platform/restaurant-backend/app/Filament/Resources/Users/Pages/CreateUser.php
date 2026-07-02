<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\AdminActivityLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * `role` and `is_active` are deliberately excluded from User's
     * #[Fillable(...)] list (a privilege-escalation guard against the
     * customer-facing API — see App\Models\User), so the default
     * mass-assignment-guarded `new Model($data)` would silently drop both
     * here too. This form is reached only by UserPolicy::create()
     * (super_admin only), so forceFill() is safe and necessary.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new (static::getModel());
        $record->forceFill($data)->save();

        return $record;
    }

    protected function afterCreate(): void
    {
        app(AdminActivityLogger::class)->log(
            actor: Auth::user(),
            action: 'user.created',
            subject: $this->record,
            description: "Created admin account {$this->record->email} with role {$this->record->role->value}.",
        );
    }
}
