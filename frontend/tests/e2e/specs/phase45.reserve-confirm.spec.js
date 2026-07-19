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
  findMyReservationRow,
  findVendorReservationRow,
  loginOwnerVendor,
  loginReserver,
  logout,
  openMarketplaceItem,
  openMyReservations,
  openOrganizerReservationDetail,
  openOrganizerReservationQueue,
  openVendorReservations,
  readPhase45EnvFixture,
  reserveOpenMarketplaceItem,
  snapshotFixtureBooking,
  submitOrganizerAction,
  loginAsOrganizer,
} from '../helpers/phase45-journey.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 4.5 — successful reserve and Organizer confirm', function () {
  let driver;
  let fixture;
  let organizerToken;
  let bookingSnapshot;
  let publicReference;

  before(async function () {
    this.timeout(120000);
    fixture = createPhase45Fixtures();
    process.env.E2E_P45_EVENT_ID = String(fixture.event_id);
    process.env.E2E_P45_VENDOR_BOOKING_ID = String(fixture.vendor_booking_id);
    process.env.E2E_P45_SUCCESS_ITEM_ID = String(fixture.success_item_id);
    process.env.E2E_P45_RESERVER_EMAIL = fixture.reserver_email;
    process.env.E2E_P45_RESERVER_PASSWORD = fixture.reserver_password;
    process.env.E2E_VENDOR_EMAIL = fixture.vendor_email;
    process.env.E2E_VENDOR_PASSWORD = fixture.vendor_password;
    process.env.E2E_ORGANIZER_EMAIL = fixture.organizer_email;
    process.env.E2E_ORGANIZER_PASSWORD = fixture.organizer_password;
    fixture = ({
	...readPhase45EnvFixture(),
	eventId: fixture.event_id,
	vendorBookingId: fixture.vendor_booking_id,
	successItemId: fixture.success_item_id,
	reserverEmail: fixture.reserver_email,
	reserverPassword: fixture.reserver_password,
	vendorEmail: fixture.vendor_email,
	vendorPassword: fixture.vendor_password,
	organizerEmail: fixture.organizer_email,
	organizerPassword: fixture.organizer_password
});

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

  it('community reserves → Organizer confirms charge → community/vendor refresh', async function () {
    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.successItemId);
    publicReference = await reserveOpenMarketplaceItem(driver);
    assert.match(publicReference, /^RSV-/);

    await openMyReservations(driver);
    const myRow = await findMyReservationRow(driver, publicReference);
    assert.equal(await myRow.getAttribute('data-reservation-status'), 'pending_charge');
    assert.equal(await myRow.getAttribute('data-charge-status'), 'required');

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerReservationQueue(driver, fixture.eventId);
    await openOrganizerReservationDetail(driver, publicReference);
    await submitOrganizerAction(driver, 'confirm', 'E2E-P45 cash received at counter');
    await assertAuditActionPresent(driver, 'charge_confirmation_recorded');

    const detail = await apiRequest(organizerToken, 'GET', `/organizer/item-reservations/${publicReference}`);
    assert.equal(detail.status, 200);
    assert.equal(detail.body.reservation.reservation_status, 'confirmed');
    assert.equal(detail.body.reservation.charge_status, 'confirmed');
    assert.equal(String(detail.body.reservation.service_fee_amount), '15.00');

    await logout(driver, { management: true });
    await loginReserver(driver, fixture);
    await openMyReservations(driver);
    const refreshed = await findMyReservationRow(driver, publicReference);
    assert.equal(await refreshed.getAttribute('data-reservation-status'), 'confirmed');
    assert.equal(await refreshed.getAttribute('data-charge-status'), 'confirmed');

    await logout(driver);
    await loginOwnerVendor(driver, fixture);
    await openVendorReservations(driver);
    const vendorRow = await findVendorReservationRow(driver, publicReference);
    assert.equal(await vendorRow.getAttribute('data-reservation-status'), 'confirmed');

    const active = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/events/${fixture.eventId}/item-reservations?reservation_status=confirmed`,
    );
    assert.equal(active.status, 200);
    const matches = (active.body.data || []).filter((row) => row.public_reference === publicReference);
    assert.equal(matches.length, 1);

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });
});
