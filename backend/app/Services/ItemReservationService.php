<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Models\User;
use App\Models\VendorItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ItemReservationService
{
    private const MAX_REFERENCE_ATTEMPTS = 5;

    public function __construct(
        private readonly ItemReservationReferenceGenerator $referenceGenerator,
        private readonly ItemReservationDuplicateKeyDetector $duplicateKeyDetector,
    ) {}

    public function create(User $actor, int $vendorItemId): ItemReservation
    {
        if ($actor->role !== 'community') {
            throw new AuthorizationException('Only community users may reserve items.');
        }

        return DB::transaction(function () use ($actor, $vendorItemId) {
            $item = VendorItem::query()
                ->whereKey($vendorItemId)
                ->lockForUpdate()
                ->first();

            if (! $item || $item->status !== 'active') {
                throw (new ModelNotFoundException)->setModel(VendorItem::class, [$vendorItemId]);
            }

            if ((int) $item->user_id === (int) $actor->id) {
                throw new AuthorizationException('You cannot reserve your own item.');
            }

            $booking = MarketplaceEligibility::upcomingApprovedBookingForUser(
                (int) $item->user_id,
                lockForUpdate: true,
            );

            if (! $booking || ! $booking->carbootEvent) {
                throw (new ModelNotFoundException)->setModel(VendorItem::class, [$vendorItemId]);
            }

            $event = $booking->carbootEvent;
            if ($event->item_reservation_service_fee === null) {
                throw new DomainConflictException(
                    'Item reservations are not configured for the eligible event.',
                    'item_reservation_fee_not_configured',
                );
            }

            if (ItemReservation::query()
                ->where('vendor_item_id', $item->id)
                ->active()
                ->exists()) {
                throw $this->alreadyReserved();
            }

            $fee = $event->item_reservation_service_fee;
            $zeroFee = bccomp($fee, '0.00', 2) === 0;
            $reservationStatus = $zeroFee
                ? ItemReservation::STATUS_CONFIRMED
                : ItemReservation::STATUS_PENDING_CHARGE;
            $chargeStatus = $zeroFee
                ? ItemReservation::CHARGE_NOT_REQUIRED
                : ItemReservation::CHARGE_REQUIRED;

            $reservation = $this->insertWithReferenceRetries([
                'vendor_item_id' => $item->id,
                'reserving_user_id' => $actor->id,
                'vendor_user_id' => $item->user_id,
                'carboot_event_id' => $event->id,
                'vendor_booking_id' => $booking->id,
                'reservation_status' => $reservationStatus,
                'active_lock' => 1,
                'service_fee_amount' => $fee,
                'service_fee_currency' => 'MYR',
                'charge_status' => $chargeStatus,
                'item_name_snapshot' => $item->name,
            ]);

            ItemReservationAudit::query()->create([
                'item_reservation_id' => $reservation->id,
                'actor_user_id' => $actor->id,
                'action' => ItemReservationAudit::ACTION_CREATED,
                'to_reservation_status' => $reservationStatus,
                'to_charge_status' => $chargeStatus,
                'note' => $zeroFee
                    ? 'Reservation confirmed immediately because no service fee was required.'
                    : 'Reservation created pending manual service-fee confirmation.',
                'metadata' => [
                    'service_fee_amount' => $fee,
                    'service_fee_currency' => 'MYR',
                    'carboot_event_id' => $event->id,
                ],
            ]);

            return $reservation->load([
                'carbootEvent',
                'vendorUser.businessProfile',
                'reservingUser',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertWithReferenceRetries(array $attributes): ItemReservation
    {
        for ($attempt = 1; $attempt <= self::MAX_REFERENCE_ATTEMPTS; $attempt++) {
            try {
                return ItemReservation::query()->create([
                    ...$attributes,
                    'public_reference' => $this->referenceGenerator->generate(),
                ]);
            } catch (QueryException $exception) {
                if ($this->duplicateKeyDetector->isActiveLockViolation($exception)) {
                    throw $this->alreadyReserved();
                }

                if ($this->duplicateKeyDetector->isPublicReferenceViolation($exception)) {
                    if ($attempt < self::MAX_REFERENCE_ATTEMPTS) {
                        continue;
                    }

                    throw new DomainConflictException(
                        'A unique reservation reference could not be generated.',
                        'reservation_reference_generation_failed',
                    );
                }

                throw $exception;
            }
        }

        throw new DomainConflictException(
            'A unique reservation reference could not be generated.',
            'reservation_reference_generation_failed',
        );
    }

    private function alreadyReserved(): DomainConflictException
    {
        return new DomainConflictException(
            'This item already has an active reservation.',
            'item_already_reserved',
        );
    }
}
