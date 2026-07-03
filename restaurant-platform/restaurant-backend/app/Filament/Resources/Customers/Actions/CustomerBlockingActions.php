<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Actions;

use App\Models\User;
use App\Services\CustomerBlockingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Shared between App\Filament\Resources\Customers\Tables\CustomersTable
 * (row actions) and Pages\ViewCustomer (header actions) — one definition,
 * so both surfaces stay in sync. Both actions go through
 * App\Services\CustomerBlockingService, never touch `is_active`/
 * `blocked_reason` directly, and check the same
 * App\Policies\UserPolicy::block()/unblock() ability the service's caller
 * is expected to already satisfy (this Resource's own authorization is
 * separate — see CustomerResource's docblock).
 */
final class CustomerBlockingActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::block(), self::unblock()];
    }

    public static function block(): Action
    {
        return Action::make('block')
            ->label('Block account')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('The customer will be logged out everywhere immediately and unable to log in until unblocked.')
            ->visible(fn (User $record): bool => ! $record->isBlocked() && Gate::allows('block', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (User $record, array $data): void {
                app(CustomerBlockingService::class)->block($record, $data['reason'], Auth::user());

                Notification::make()
                    ->success()
                    ->title("{$record->name}'s account has been blocked.")
                    ->send();
            });
    }

    public static function unblock(): Action
    {
        return Action::make('unblock')
            ->label('Unblock account')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->isBlocked() && Gate::allows('unblock', $record))
            ->action(function (User $record): void {
                app(CustomerBlockingService::class)->unblock($record, Auth::user());

                Notification::make()
                    ->success()
                    ->title("{$record->name}'s account has been unblocked.")
                    ->send();
            });
    }
}
