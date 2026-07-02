<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn (User $record) => UserResource::logDeletion($record)),
        ];
    }

    /**
     * Same reasoning as CreateUser::handleRecordCreation(): `role` and
     * `is_active` aren't mass-assignable, so the default `$record->update($data)`
     * would silently drop any change to either field.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        return $record;
    }

    /**
     * Records a role/activation change to the admin audit trail — the two
     * fields sensitive enough to matter here (see docs/ADMIN_PANEL.md).
     * getChanges() reflects the save that just happened, so this only
     * fires when one of them actually changed, not on every save.
     */
    protected function afterSave(): void
    {
        $changes = array_intersect_key($this->record->getChanges(), array_flip(['role', 'is_active']));

        if ($changes === []) {
            return;
        }

        app(AdminActivityLogger::class)->log(
            actor: Auth::user(),
            action: 'user.updated',
            subject: $this->record,
            description: "Updated admin account {$this->record->email}.",
            metadata: $changes,
        );
    }
}
