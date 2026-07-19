<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ItemReservationCancellationService
{
    public const ACTOR_RESERVING_USER = 'reserving_user';

    public const ACTOR_VENDOR = 'vendor';

    public function cancel(
        ItemReservation $reservation,
        User $actor,
        string $actorType,
        ?string $reason,
        bool $noRefundAcknowledged = false,
    ): ItemReservation {
        return DB::transaction(function () use ($reservation, $actor, $actorType, $reason, $noRefundAcknowledged) {
            $locked = ItemReservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize($locked, $actor, $actorType);
            $this->assertCancellable($locked, $actorType, $reason, $noRefundAcknowledged);

            $fromReservationStatus = $locked->reservation_status;
            $fromChargeStatus = $locked->charge_status;
            $toChargeStatus = ItemReservationLifecycleService::chargeStatusAfterTermination(
                $fromChargeStatus,
            );

            $locked->update([
                'reservation_status' => ItemReservation::STATUS_CANCELLED,
                'active_lock' => null,
                'charge_status' => $toChargeStatus,
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            ItemReservationAudit::query()->create([
                'item_reservation_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'action' => ItemReservationAudit::ACTION_CANCELLED,
                'from_reservation_status' => $fromReservationStatus,
                'to_reservation_status' => ItemReservation::STATUS_CANCELLED,
                'from_charge_status' => $fromChargeStatus,
                'to_charge_status' => $toChargeStatus,
                'note' => $reason,
                'metadata' => $fromChargeStatus === ItemReservation::CHARGE_CONFIRMED
                    ? ['no_refund_acknowledged' => true]
                    : null,
            ]);

            return $locked->load([
                'carbootEvent',
                'vendorUser.businessProfile',
                'reservingUser',
            ]);
        });
    }

    private function assertCancellable(
        ItemReservation $reservation,
        string $actorType,
        ?string $reason,
        bool $noRefundAcknowledged,
    ): void {
        if ($reservation->reservation_status === ItemReservation::STATUS_PENDING_CHARGE) {
            return;
        }

        // Reserving users keep the frozen Phase 4.2 pending-only rule.
        if ($actorType !== self::ACTOR_VENDOR
            || $reservation->reservation_status !== ItemReservation::STATUS_CONFIRMED) {
            throw new DomainConflictException(
                'Only a pending-charge reservation may be cancelled through this path.',
                'reservation_not_pending',
            );
        }

        if ($reason === null || trim($reason) === '') {
            throw new DomainConflictException(
                'A cancellation reason is required for a confirmed reservation.',
                'cancellation_reason_required',
            );
        }

        if ($reservation->charge_status === ItemReservation::CHARGE_CONFIRMED
            && ! $noRefundAcknowledged) {
            throw new DomainConflictException(
                'Cancelling a reservation with a confirmed charge requires an explicit no-refund acknowledgement.',
                'no_refund_acknowledgement_required',
            );
        }
    }

    private function authorize(
        ItemReservation $reservation,
        User $actor,
        string $actorType,
    ): void {
        $authorized = match ($actorType) {
            self::ACTOR_RESERVING_USER => (int) $reservation->reserving_user_id === (int) $actor->id,
            self::ACTOR_VENDOR => (int) $reservation->vendor_user_id === (int) $actor->id,
            default => false,
        };

        if (! $authorized) {
            throw (new ModelNotFoundException)->setModel(
                ItemReservation::class,
                [$reservation->public_reference],
            );
        }
    }
}
