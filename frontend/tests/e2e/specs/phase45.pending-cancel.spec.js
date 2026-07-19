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
  findMyReservationRow,
  loginCompetitor,
  loginReserver,
  logout,
  openMarketplaceItem,
  openMyReservations,
  readPhase45EnvFixture,
  reserveOpenMarketplaceItem,
  snapshotFixtureBooking,
} from '../helpers/phase45-journey.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 4.5 — pending community cancellation reopens item', function () {
  let driver;
  let fixture;
  let organizerToken;
  let bookingSnapshot;
  let publicReference;

  before(async function () {
    this.timeout(120000);
    const created = createPhase45Fixtures();
    fixture = {
      ...readPhase45EnvFixture(),
      eventId: created.event_id,
      vendorBookingId: created.vendor_booking_id,
      cancelItemId: created.cancel_item_id,
      reserverEmail: created.reserver_email,
      reserverPassword: created.reserver_password,
      competitorEmail: created.competitor_email,
      competitorPassword: created.competitor_password,
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

  it('pending cancel clears hold and second reserver succeeds', async function () {
    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.cancelItemId);
    publicReference = await reserveOpenMarketplaceItem(driver);

    await openMyReservations(driver);
    const row = await findMyReservationRow(driver, publicReference);
    const cancel = await row.findElement(By.css('[data-testid="my-reservation-cancel"]'));
    await cancel.click();
    await waitForTestId(driver, 'my-reservation-cancel-modal', 10000);
    const reason = await waitForTestId(driver, 'my-reservation-cancel-reason', 5000);
    await reason.sendKeys('E2E-P45 changed plans');
    const confirm = await waitForTestId(driver, 'my-reservation-cancel-confirm', 5000);
    await confirm.click();

    await driver.wait(async () => {
      const refreshed = await findMyReservationRow(driver, publicReference);
      return (await refreshed.getAttribute('data-reservation-status')) === 'cancelled';
    }, 20000);

    const marketplace = await apiRequest(null, 'GET', `/marketplace/items/${fixture.cancelItemId}`);
    assert.equal(marketplace.status, 200);
    assert.equal(marketplace.body.item.is_reservable, true);
    assert.equal(marketplace.body.item.has_active_reservation, false);

    await logout(driver);
    await loginCompetitor(driver, fixture);
    await openMarketplaceItem(driver, fixture.cancelItemId);
    const secondReference = await reserveOpenMarketplaceItem(driver);
    assert.match(secondReference, /^RSV-/);
    assert.notEqual(secondReference, publicReference);

    const active = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/events/${fixture.eventId}/item-reservations`,
    );
    const activeRows = (active.body.data || []).filter(
      (row) => ['pending_charge', 'confirmed'].includes(row.reservation_status)
        && row.item_name === 'E2E-P45 Cancel Radio',
    );
    assert.equal(activeRows.length, 1);
    assert.equal(activeRows[0].public_reference, secondReference);

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });
});
