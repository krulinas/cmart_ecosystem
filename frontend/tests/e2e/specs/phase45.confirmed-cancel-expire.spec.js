import { strict as assert } from 'node:assert';
import { after, before, describe, it } from 'mocha';
import {
  assertPhase45ResidueClean,
  cleanupPhase45Fixtures,
  createPhase45Fixtures,
} from '../helpers/phase45-fixtures.js';
import {
  apiLogin,
  apiRequest,
  assertAuditActionPresent,
  assertBookingUnchanged,
  loginAsOrganizer,
  loginReserver,
  logout,
  openMarketplaceItem,
  openOrganizerReservationDetail,
  openOrganizerReservationQueue,
  readPhase45EnvFixture,
  reserveOpenMarketplaceItem,
  snapshotFixtureBooking,
  submitOrganizerAction,
} from '../helpers/phase45-journey.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 4.5 — confirmed no-refund cancel and manual expiry', function () {
  let driver;
  let fixture;
  let organizerToken;
  let bookingSnapshot;

  before(async function () {
    this.timeout(120000);
    const created = createPhase45Fixtures();
    fixture = {
      ...readPhase45EnvFixture(),
      eventId: created.event_id,
      vendorBookingId: created.vendor_booking_id,
      expiryItemId: created.expiry_item_id,
      accessItemId: created.access_item_id,
      reserverEmail: created.reserver_email,
      reserverPassword: created.reserver_password,
      organizerEmail: created.organizer_email,
      organizerPassword: created.organizer_password,
    };
    organizerToken = await apiLogin(fixture.organizerEmail, fixture.organizerPassword);
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

  it('Organizer cancels confirmed hold with no-refund acknowledgement and retains charge evidence', async function () {
    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.expiryItemId);
    const reference = await reserveOpenMarketplaceItem(driver);

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerReservationQueue(driver, fixture.eventId);
    await openOrganizerReservationDetail(driver, reference);
    await submitOrganizerAction(driver, 'confirm', 'E2E-P45 fee paid for cancel path');
    await assertAuditActionPresent(driver, 'charge_confirmation_recorded');

    await submitOrganizerAction(driver, 'cancel', 'E2E-P45 confirmed cancel no refund', {
      acknowledgeNoRefund: true,
    });
    await assertAuditActionPresent(driver, 'reservation_cancelled');

    const detail = await apiRequest(organizerToken, 'GET', `/organizer/item-reservations/${reference}`);
    assert.equal(detail.status, 200);
    assert.equal(detail.body.reservation.reservation_status, 'cancelled');
    assert.equal(detail.body.reservation.charge_status, 'confirmed');
    assert.ok(
      detail.body.reservation.charge_confirmation?.note
        || detail.body.reservation.charge_confirmation_note,
    );

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });

  it('Organizer manually expires a confirmed reservation with audit evidence', async function () {
    await logout(driver, { management: true });
    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.accessItemId);
    const reference = await reserveOpenMarketplaceItem(driver);

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerReservationQueue(driver, fixture.eventId);
    await openOrganizerReservationDetail(driver, reference);
    await submitOrganizerAction(driver, 'confirm', 'E2E-P45 fee paid for expiry path');
    await submitOrganizerAction(driver, 'expire', 'E2E-P45 manual expiry after no-show');
    await assertAuditActionPresent(driver, 'reservation_expired');

    const detail = await apiRequest(organizerToken, 'GET', `/organizer/item-reservations/${reference}`);
    assert.equal(detail.status, 200);
    assert.equal(detail.body.reservation.reservation_status, 'expired');
    assert.equal(String(detail.body.reservation.service_fee_amount), '15.00');

    const marketplace = await apiRequest(null, 'GET', `/marketplace/items/${fixture.accessItemId}`);
    assert.equal(marketplace.status, 200);
    assert.equal(marketplace.body.item.is_reservable, true);

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });
});
