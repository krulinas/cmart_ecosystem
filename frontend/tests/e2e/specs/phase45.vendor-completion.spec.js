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
  findVendorReservationRow,
  loginAsOrganizer,
  loginOwnerVendor,
  loginReserver,
  logout,
  openMarketplaceItem,
  openOrganizerReservationDetail,
  openOrganizerReservationQueue,
  openVendorReservations,
  readPhase45EnvFixture,
  reserveOpenMarketplaceItem,
  snapshotFixtureBooking,
  submitOrganizerAction,
} from '../helpers/phase45-journey.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 4.5 — vendor completion archives item', function () {
  let driver;
  let fixture;
  let organizerToken;
  let vendorToken;
  let bookingSnapshot;
  let publicReference;

  before(async function () {
    this.timeout(120000);
    const created = createPhase45Fixtures();
    fixture = {
      ...readPhase45EnvFixture(),
      eventId: created.event_id,
      vendorBookingId: created.vendor_booking_id,
      completionItemId: created.completion_item_id,
      reserverEmail: created.reserver_email,
      reserverPassword: created.reserver_password,
      vendorEmail: created.vendor_email,
      vendorPassword: created.vendor_password,
      organizerEmail: created.organizer_email,
      organizerPassword: created.organizer_password,
    };
    organizerToken = await apiLogin(fixture.organizerEmail, fixture.organizerPassword);
    vendorToken = await apiLogin(fixture.vendorEmail, fixture.vendorPassword);
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

  it('vendor completes confirmed reservation, item leaves marketplace, repeat completion rejected', async function () {
    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.completionItemId);
    publicReference = await reserveOpenMarketplaceItem(driver);

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerReservationQueue(driver, fixture.eventId);
    await openOrganizerReservationDetail(driver, publicReference);
    await submitOrganizerAction(driver, 'confirm', 'E2E-P45 fee for completion path');

    await logout(driver, { management: true });
    await loginOwnerVendor(driver, fixture);
    await openVendorReservations(driver);
    const row = await findVendorReservationRow(driver, publicReference);
    const complete = await row.findElement(By.css('[data-testid="vendor-reservation-complete"]'));
    await complete.click();
    await waitForTestId(driver, 'vendor-reservation-complete-modal', 10000);
    const confirm = await waitForTestId(driver, 'vendor-reservation-complete-confirm', 5000);
    await confirm.click();

    await driver.wait(async () => {
      const refreshed = await findVendorReservationRow(driver, publicReference);
      return (await refreshed.getAttribute('data-reservation-status')) === 'completed';
    }, 20000);

    const detail = await apiRequest(vendorToken, 'GET', `/vendor/item-reservations/${publicReference}`);
    assert.equal(detail.status, 200);
    assert.equal(detail.body.reservation.reservation_status, 'completed');
    assert.equal(detail.body.reservation.charge_status, 'confirmed');
    assert.equal(String(detail.body.reservation.service_fee_amount), '15.00');

    const marketplace = await apiRequest(null, 'GET', `/marketplace/items/${fixture.completionItemId}`);
    assert.equal(marketplace.status, 404);

    const repeat = await apiRequest(
      vendorToken,
      'POST',
      `/vendor/item-reservations/${publicReference}/complete`,
    );
    assert.equal(repeat.status, 409, JSON.stringify(repeat.body));

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });
});
