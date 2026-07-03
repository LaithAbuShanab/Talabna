<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Actions;

use App\DataTransferObjects\Order\TransitionOrderStatusData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Exceptions\OrderStatusTransitionException;
use App\Models\Order;
use App\Services\OrderStatusTransitionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Every lifecycle action an admin can take on an order, shared between
 * App\Filament\Resources\Orders\Tables\OrdersTable (row actions, for the
 * "fast" part of "شاشة سريعة وواضحة") and
 * App\Filament\Resources\Orders\Pages\ViewOrder (header actions, for the
 * detail page's own "إجراءات تغيير الحالة" requirement) — one definition,
 * so the two surfaces can never drift apart.
 *
 * Every action funnels through App\Services\OrderStatusTransitionService;
 * none of them ever assigns `$record->status` directly. `->visible()` here
 * mirrors the exact same policy ability the service checks internally
 * (via Gate::allows(), not a hand-rolled duplicate of the rule) purely so
 * the button is only offered when it would actually succeed — the service
 * (and OrderPolicy) remain the real, server-side enforcement regardless of
 * what's rendered, same as every other admin resource in this project.
 * `requiresConfirmation()` on every action satisfies "confirmations for
 * sensitive actions": every one of these changes an order's lifecycle
 * state, which is exactly the kind of action worth an "are you sure?" — not
 * just the destructive ones.
 */
final class OrderStatusActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::accept(),
            self::reject(),
            self::startPreparing(),
            self::markReady(),
            self::outForDelivery(),
            self::markDelivered(),
            self::cancel(),
        ];
    }

    public static function accept(): Action
    {
        return Action::make('accept')
            ->label('Accept order')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && Gate::allows('manage', $record))
            ->schema([
                TextInput::make('estimated_preparation_minutes')
                    ->label('Estimated preparation time (minutes)')
                    ->helperText('Sets the order\'s expected delivery/pickup time.')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(20),
            ])
            ->action(function (Order $record, array $data): void {
                self::transition($record, OrderStatus::Accepted, estimatedPreparationMinutes: (int) $data['estimated_preparation_minutes']);
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && Gate::allows('manage', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Order $record, array $data): void {
                self::transition($record, OrderStatus::Rejected, reason: $data['reason']);
            });
    }

    public static function startPreparing(): Action
    {
        return Action::make('startPreparing')
            ->label('Start preparing')
            ->icon(Heroicon::OutlinedFire)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Accepted && Gate::allows('manage', $record))
            ->action(fn (Order $record) => self::transition($record, OrderStatus::Preparing));
    }

    public static function markReady(): Action
    {
        return Action::make('markReady')
            ->label('Mark ready')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Preparing && Gate::allows('manage', $record))
            ->action(fn (Order $record) => self::transition($record, OrderStatus::Ready));
    }

    public static function outForDelivery(): Action
    {
        return Action::make('outForDelivery')
            ->label('Out for delivery')
            ->icon(Heroicon::OutlinedTruck)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(
                fn (Order $record): bool => $record->status === OrderStatus::Ready
                    && $record->delivery_type === DeliveryType::Delivery
                    && Gate::allows('manage', $record)
            )
            ->action(fn (Order $record) => self::transition($record, OrderStatus::OutForDelivery));
    }

    public static function markDelivered(): Action
    {
        return Action::make('markDelivered')
            ->label('Mark delivered')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(function (Order $record): bool {
                if (! Gate::allows('manage', $record)) {
                    return false;
                }

                return ($record->status === OrderStatus::Ready && $record->delivery_type === DeliveryType::Pickup)
                    || $record->status === OrderStatus::OutForDelivery;
            })
            ->action(fn (Order $record) => self::transition($record, OrderStatus::Delivered));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This cannot be undone. The order will move to a final, cancelled state.')
            ->visible(fn (Order $record): bool => ! $record->status->isTerminal() && self::canCancel($record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Order $record, array $data): void {
                self::transition($record, OrderStatus::Cancelled, reason: $data['reason']);
            });
    }

    /**
     * Mirrors OrderStatusTransitionService::authorize()'s cancellation
     * branch exactly: which ability gates a cancellation depends on the
     * order's *current* status (plain `manage()` through `preparing`,
     * "special permission" at `ready`, "very special permission" at
     * `out_for_delivery`) — see App\Policies\OrderPolicy.
     */
    private static function canCancel(Order $record): bool
    {
        return match ($record->status) {
            OrderStatus::Ready => Gate::allows('cancelAtReadyStage', $record),
            OrderStatus::OutForDelivery => Gate::allows('cancelAtOutForDeliveryStage', $record),
            default => Gate::allows('manage', $record),
        };
    }

    private static function transition(
        Order $record,
        OrderStatus $to,
        ?string $reason = null,
        ?int $estimatedPreparationMinutes = null,
    ): void {
        try {
            app(OrderStatusTransitionService::class)->transition($record, new TransitionOrderStatusData(
                to: $to,
                actor: Auth::user(),
                reason: $reason,
                estimatedPreparationMinutes: $estimatedPreparationMinutes,
            ));

            Notification::make()
                ->success()
                ->title("Order #{$record->order_number} moved to \"{$to->getLabel()}\".")
                ->send();
        } catch (OrderStatusTransitionException $exception) {
            Notification::make()
                ->danger()
                ->title('Could not update the order')
                ->body($exception->getMessage())
                ->send();
        }
    }
}
