import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { after, before, describe, it } from 'mocha';
import {
  assertPhase45ResidueClean,
  cleanupPhase45Fixtures,
  createPhase45Fixtures,
} from '../helpers/phase45-fixtures.js';
import {
  apiLogin,
  apiRequest,
  assertBookingUnchanged,
  attemptReserveExpectConflict,
  loginReserver,
  openMarketplaceItem,
  readPhase45EnvFixture,
  snapshotFixtureBooking,
} from '../helpers/phase45-journey.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

async function ensureAlreadyReserved(driver) {
  await waitForTestId(driver, 'marketplace-item-already-reserved', 15000);
}

describe('Phase 4.5 — stale-view second-reserver conflict', function () {
  let driver;
  let fixture;
  let organizerToken;
  let bookingSnapshot;
  let reserverToken;

  before(async function () {
    this.timeout(120000);
    const created = createPhase45Fixtures();
    fixture = {
      ...readPhase45EnvFixture(),
      eventId: created.event_id,
      vendorBookingId: created.vendor_booking_id,
      conflictItemId: created.conflict_item_id,
      heldReservationReference: created.held_reservation_reference,
      reserverEmail: created.reserver_email,
      reserverPassword: created.reserver_password,
      organizerEmail: created.organizer_email,
      organizerPassword: created.organizer_password,
    };
    organizerToken = await apiLogin(fixture.organizerEmail, fixture.organizerPassword);
    reserverToken = await apiLogin(fixture.reserverEmail, fixture.reserverPassword);
    bookingSnapshot = await snapshotFixtureBooking(organizerToken, fixture.vendorBookingId);
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  after(async function () {
    this.timeout(60000);
    await quitDriver(driver);
    cleanupPhase45Fixtures();
    assertPhase45ResidueClean();
  });

  it('second reserver receives 409 item_already_reserved with one active row/audit', async function () {
    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.conflictItemId);

    // Stale CTA may still show Reserve briefly; force a conflict attempt.
    const ctaButtons = await driver.findElements(By.css('[data-testid="marketplace-reserve-cta"]'));
    if (ctaButtons.length) {
      await attemptReserveExpectConflict(driver);
    } else {
      await ensureAlreadyReserved(driver);
    }

    const conflict = await apiRequest(reserverToken, 'POST', '/reservations', {
      vendor_item_id: fixture.conflictItemId,
    });
    assert.equal(conflict.status, 409, JSON.stringify(conflict.body));
    assert.equal(conflict.body.error, 'item_already_reserved');

    const queue = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/events/${fixture.eventId}/item-reservations`,
    );
    assert.equal(queue.status, 200);
    const activeForItem = (queue.body.data || []).filter(
      (row) => row.public_reference === fixture.heldReservationReference
        && ['pending_charge', 'confirmed'].includes(row.reservation_status),
    );
    assert.equal(activeForItem.length, 1);
    assert.equal(activeForItem[0].item_name, 'E2E-P45 Conflict Lamp');

    const audits = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/item-reservations/${fixture.heldReservationReference}/audits`,
    );
    assert.equal(audits.status, 200);
    // Seeded hold has no audit; conflict attempt must not create a second active reservation.
    const activeLocks = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/events/${fixture.eventId}/item-reservations?reservation_status=pending_charge`,
    );
    const pendingForItem = (activeLocks.body.data || []).filter(
      (row) => row.public_reference === fixture.heldReservationReference,
    );
    assert.equal(pendingForItem.length, 1);

    await openMarketplaceItem(driver, fixture.conflictItemId);
    await ensureAlreadyReserved(driver);

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });
});
