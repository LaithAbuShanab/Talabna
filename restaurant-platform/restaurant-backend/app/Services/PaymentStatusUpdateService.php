<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place a Payment's status (and its parent Order's mirrored
 * `payment_status`) is ever changed — same "row lock, transaction, dispatch
 * only after commit" shape as App\Services\OrderStatusTransitionService, so
 * a listener (e.g. a customer push notification) can never observe a
 * change that later rolls back. Not wired to any HTTP endpoint or Filament
 * action yet — no payment gateway webhook exists in this codebase today —
 * this is the seam a future one calls into; see docs/NOTIFICATIONS.md.
 */
final class PaymentStatusUpdateService
{
    public function update(Payment $payment, PaymentStatus $to, ?User $actor = null, ?string $note = null): Payment
    {
        $from = null;

        $updated = DB::transaction(function () use ($payment, $to, $note, &$from): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $from = $locked->status;

            $locked->status = $to;

            if ($to === PaymentStatus::Paid) {
                $locked->paid_at = now();
            }

            if ($note !== null) {
                $locked->notes = $note;
            }

            $locked->save();

            $locked->order()->update(['payment_status' => $to]);

            return $locked;
        });

        PaymentStatusChanged::dispatch($updated->order()->first(), $updated, $from, $to, $actor);

        return $updated->fresh();
    }
}
