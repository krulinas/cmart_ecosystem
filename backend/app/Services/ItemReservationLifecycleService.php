<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4.3 — Organizer manual charge lifecycle.
 *
 * Confirmation and waiver record a manual, off-platform outcome only.
 * No money is processed and booking invoices are never touched.
 */
class ItemReservationLifecycleService
{
    public function confirmCharge(
        ItemReservation $reservation,
        User $actor,
        string $note,
    ): ItemReservation {
        return DB::transaction(function () use ($reservation, $actor, $note) {
            $locked = $this->lock($reservation);
            $this->assertChargeResolvable($locked);

            $confirmedAt = now();

            $locked->update([
                'reservation_status' => ItemReservation::STATUS_CONFIRMED,
                'charge_status' => ItemReservation::CHARGE_CONFIRMED,
                'charge_confirmation_note' => $note,
                'charge_confirmed_by' => $actor->id,
                'charge_confirmed_at' => $confirmedAt,
            ]);

            $this->audit($locked, $actor, ItemReservationAudit::ACTION_CHARGE_CONFIRMATION_RECORDED, [
                'from_reservation_status' => ItemReservation::STATUS_PENDING_CHARGE,
                'to_reservation_status' => ItemReservation::STATUS_CONFIRMED,
                'from_charge_status' => ItemReservation::CHARGE_REQUIRED,
                'to_charge_status' => ItemReservation::CHARGE_CONFIRMED,
                'note' => $note,
                'metadata' => [
                    'manual_off_platform_payment' => true,
                    'service_fee_amount' => $locked->service_fee_amount,
                    'service_fee_currency' => $locked->service_fee_currency,
                ],
            ]);
            $this->audit($locked, $actor, ItemReservationAudit::ACTION_CONFIRMED, [
                'from_reservation_status' => ItemReservation::STATUS_PENDING_CHARGE,
                'to_reservation_status' => ItemReservation::STATUS_CONFIRMED,
                'from_charge_status' => ItemReservation::CHARGE_REQUIRED,
                'to_charge_status' => ItemReservation::CHARGE_CONFIRMED,
                'note' => 'Reservation confirmed after Organizer recorded the manual service-fee payment.',
            ]);

            return $this->reload($locked);
        });
    }

    public function waiveCharge(
        ItemReservation $reservation,
        User $actor,
        string $reason,
    ): ItemReservation {
        return DB::transaction(function () use ($reservation, $actor, $reason) {
            $locked = $this->lock($reservation);
            $this->assertChargeResolvable($locked);

            $waivedAt = now();

            $locked->update([
                'reservation_status' => ItemReservation::STATUS_CONFIRMED,
                'charge_status' => ItemReservation::CHARGE_WAIVED,
                'charge_waive_reason' => $reason,
                'charge_waived_by' => $actor->id,
                'charge_waived_at' => $waivedAt,
            ]);

            $this->audit($locked, $actor, ItemReservationAudit::ACTION_CHARGE_WAIVED, [
                'from_reservation_status' => ItemReservation::STATUS_PENDING_CHARGE,
                'to_reservation_status' => ItemReservation::STATUS_CONFIRMED,
                'from_charge_status' => ItemReservation::CHARGE_REQUIRED,
                'to_charge_status' => ItemReservation::CHARGE_WAIVED,
                'note' => $reason,
            ]);
            $this->audit($locked, $actor, ItemReservationAudit::ACTION_CONFIRMED, [
                'from_reservation_status' => ItemReservation::STATUS_PENDING_CHARGE,
                'to_reservation_status' => ItemReservation::STATUS_CONFIRMED,
                'from_charge_status' => ItemReservation::CHARGE_REQUIRED,
                'to_charge_status' => ItemReservation::CHARGE_WAIVED,
                'note' => 'Reservation confirmed after the Organizer waived the service fee.',
            ]);

            return $this->reload($locked);
        });
    }

    public function organizerCancel(
        ItemReservation $reservation,
        User $actor,
        string $reason,
        bool $noRefundAcknowledged,
    ): ItemReservation {
        return DB::transaction(function () use ($reservation, $actor, $reason, $noRefundAcknowledged) {
            $locked = $this->lock($reservation);
            $this->assertActive($locked);
            $this->assertNoRefundAcknowledged($locked, $noRefundAcknowledged);

            $fromReservationStatus = $locked->reservation_status;
            $fromChargeStatus = $locked->charge_status;
            $toChargeStatus = $this->chargeStatusAfterTermination($fromChargeStatus);

            $locked->update([
                'reservation_status' => ItemReservation::STATUS_CANCELLED,
                'active_lock' => null,
                'charge_status' => $toChargeStatus,
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            $this->audit($locked, $actor, ItemReservationAudit::ACTION_CANCELLED, [
                'from_reservation_status' => $fromReservationStatus,
                'to_reservation_status' => ItemReservation::STATUS_CANCELLED,
                'from_charge_status' => $fromChargeStatus,
                'to_charge_status' => $toChargeStatus,
                'note' => $reason,
                'metadata' => $fromChargeStatus === ItemReservation::CHARGE_CONFIRMED
                    ? ['no_refund_acknowledged' => true]
                    : null,
            ]);

            return $this->reload($locked);
        });
    }

    public function expire(
        ItemReservation $reservation,
        User $actor,
        string $reason,
    ): ItemReservation {
        return DB::transaction(function () use ($reservation, $actor, $reason) {
            $locked = $this->lock($reservation);
            $this->assertActive($locked);

            $fromReservationStatus = $locked->reservation_status;
            $fromChargeStatus = $locked->charge_status;
            $toChargeStatus = $this->chargeStatusAfterTermination($fromChargeStatus);

            $locked->update([
                'reservation_status' => ItemReservation::STATUS_EXPIRED,
                'active_lock' => null,
                'charge_status' => $toChargeStatus,
                'expired_by' => $actor->id,
                'expired_at' => now(),
            ]);

            $this->audit($locked, $actor, ItemReservationAudit::ACTION_EXPIRED, [
                'from_reservation_status' => $fromReservationStatus,
                'to_reservation_status' => ItemReservation::STATUS_EXPIRED,
                'from_charge_status' => $fromChargeStatus,
                'to_charge_status' => $toChargeStatus,
                'note' => $reason,
            ]);

            return $this->reload($locked);
        });
    }

    /**
     * required terminates as cancelled; resolved charge history is preserved.
     */
    public static function chargeStatusAfterTermination(string $chargeStatus): string
    {
        return $chargeStatus === ItemReservation::CHARGE_REQUIRED
            ? ItemReservation::CHARGE_CANCELLED
            : $chargeStatus;
    }

    private function lock(ItemReservation $reservation): ItemReservation
    {
        return ItemReservation::query()
            ->whereKey($reservation->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertChargeResolvable(ItemReservation $reservation): void
    {
        if ($reservation->reservation_status !== ItemReservation::STATUS_PENDING_CHARGE) {
            throw new DomainConflictException(
                'Only a pending-charge reservation may resolve its service fee.',
                'reservation_not_pending_charge',
            );
        }

        if ($reservation->charge_status !== ItemReservation::CHARGE_REQUIRED) {
            throw new DomainConflictException(
                'The service fee for this reservation is already resolved.',
                'charge_already_resolved',
            );
        }
    }

    private function assertActive(ItemReservation $reservation): void
    {
        if (! in_array($reservation->reservation_status, [
            ItemReservation::STATUS_PENDING_CHARGE,
            ItemReservation::STATUS_CONFIRMED,
        ], true)) {
            throw new DomainConflictException(
                'Only an active reservation may be cancelled or expired.',
                'reservation_not_active',
            );
        }
    }

    private function assertNoRefundAcknowledged(
        ItemReservation $reservation,
        bool $noRefundAcknowledged,
    ): void {
        if ($reservation->charge_status === ItemReservation::CHARGE_CONFIRMED
            && ! $noRefundAcknowledged) {
            throw new DomainConflictException(
                'Cancelling a reservation with a confirmed charge requires an explicit no-refund acknowledgement.',
                'no_refund_acknowledgement_required',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function audit(ItemReservation $reservation, User $actor, string $action, array $attributes): void
    {
        ItemReservationAudit::query()->create([
            'item_reservation_id' => $reservation->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            ...$attributes,
        ]);
    }

    private function reload(ItemReservation $reservation): ItemReservation
    {
        return $reservation->load([
            'carbootEvent',
            'vendorItem',
            'vendorUser.businessProfile',
            'reservingUser',
            'chargeConfirmer',
            'chargeWaiver',
            'cancelledBy',
            'expiredBy',
        ]);
    }
}
