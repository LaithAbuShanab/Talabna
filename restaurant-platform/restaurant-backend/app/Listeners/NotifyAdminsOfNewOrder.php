<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Models\User;
use Filament\Notifications\Notification;

/**
 * "إرسال database notification للإدارة عند طلب جديد" — an internal,
 * same-database write, not a call to an external push provider, so unlike
 * every Send*PushNotification listener this one never touches
 * App\Jobs\SendCustomerPushNotificationJob: "queue جميع الإشعارات
 * الخارجية" only requires explicit queuing for outbound calls to a push
 * provider. This listener class itself is a plain (non-ShouldQueue)
 * listener — but Filament's own `Notification::sendToDatabase()`
 * (App\Providers\Filament\AdminPanelProvider already enables
 * ->databaseNotifications() on the admin panel) happens to queue the
 * actual database write internally regardless (its `DatabaseNotification`
 * implements ShouldQueue); in local/test environments with
 * `QUEUE_CONNECTION=sync` that still runs inline, same effective result.
 */
final class NotifyAdminsOfNewOrder
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        $admins = User::query()
            ->whereIn('role', UserRole::adminCases())
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(__('notifications.admin_new_order.title'))
            ->body(__('notifications.admin_new_order.body', ['number' => $order->order_number]))
            ->info()
            ->sendToDatabase($admins);
    }
}
