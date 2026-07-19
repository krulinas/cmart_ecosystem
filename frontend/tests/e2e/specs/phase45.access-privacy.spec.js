import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { after, before, describe, it } from 'mocha';
import { env } from '../config/env.js';
import {
  assertPhase45ResidueClean,
  cleanupPhase45Fixtures,
  createPhase45Fixtures,
} from '../helpers/phase45-fixtures.js';
import {
  apiLogin,
  apiRequest,
  assertBookingUnchanged,
  loginAsCmartManagement,
  loginAsOrganizer,
  loginOwnerVendor,
  loginReserver,
  loginUnrelatedVendor,
  logout,
  openMarketplaceItem,
  openOrganizerReservationQueue,
  readPhase45EnvFixture,
  reserveOpenMarketplaceItem,
  snapshotFixtureBooking,
} from '../helpers/phase45-journey.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { waitForTestId, waitForUrlContains } from '../helpers/wait.js';
import { clearBrowserSession } from '../helpers/session.js';
import { setActiveDriver } from '../setup.js';

function assertNoSensitiveLeak(payload) {
  const serialized = JSON.stringify(payload).toLowerCase();
  for (const needle of ['business_phone', 'whatsapp', 'payment_proof', 'password']) {
    assert.equal(
      serialized.includes(`"${needle}"`),
      false,
      `Unexpected sensitive field "${needle}" in payload`,
    );
  }
}

describe('Phase 4.5 — access and privacy boundaries', function () {
  let driver;
  let fixture;
  let organizerToken;
  let managementToken;
  let vendorToken;
  let unrelatedToken;
  let reserverToken;
  let bookingSnapshot;
  let publicReference;

  before(async function () {
    this.timeout(120000);
    const created = createPhase45Fixtures();
    fixture = {
      ...readPhase45EnvFixture(),
      eventId: created.event_id,
      vendorBookingId: created.vendor_booking_id,
      ownerOnlyItemId: created.owner_only_item_id,
      successItemId: created.success_item_id,
      reserverEmail: created.reserver_email,
      reserverPassword: created.reserver_password,
      vendorEmail: created.vendor_email,
      vendorPassword: created.vendor_password,
      unrelatedVendorEmail: created.unrelated_vendor_email,
      unrelatedVendorPassword: created.unrelated_vendor_password,
      organizerEmail: created.organizer_email,
      organizerPassword: created.organizer_password,
      managementEmail: created.cmart_management_email,
      managementPassword: created.cmart_management_password,
    };
    organizerToken = await apiLogin(fixture.organizerEmail, fixture.organizerPassword);
    managementToken = await apiLogin(fixture.managementEmail, fixture.managementPassword);
    vendorToken = await apiLogin(fixture.vendorEmail, fixture.vendorPassword);
    unrelatedToken = await apiLogin(fixture.unrelatedVendorEmail, fixture.unrelatedVendorPassword);
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

  it('guest, owner, management, community, and unrelated vendor stay in role boundaries', async function () {
    await clearBrowserSession(driver);
    await openMarketplaceItem(driver, fixture.successItemId);
    const loginCta = await waitForTestId(driver, 'marketplace-reserve-login', 15000);
    await loginCta.click();
    await waitForUrlContains(driver, '/login', 15000);

    await loginReserver(driver, fixture);
    await openMarketplaceItem(driver, fixture.successItemId);
    publicReference = await reserveOpenMarketplaceItem(driver);

    const mine = await apiRequest(reserverToken, 'GET', `/reservations/${publicReference}`);
    assert.equal(mine.status, 200);
    assertNoSensitiveLeak(mine.body);

    const ownerPrivate = await apiRequest(vendorToken, 'GET', `/vendor/items/${fixture.ownerOnlyItemId}`);
    assert.equal(ownerPrivate.status, 200);

    const unrelatedItem = await apiRequest(unrelatedToken, 'GET', `/vendor/items/${fixture.ownerOnlyItemId}`);
    assert.ok([403, 404].includes(unrelatedItem.status), JSON.stringify(unrelatedItem.body));

    const unrelatedReservation = await apiRequest(
      unrelatedToken,
      'GET',
      `/vendor/item-reservations/${publicReference}`,
    );
    assert.ok([403, 404].includes(unrelatedReservation.status), JSON.stringify(unrelatedReservation.body));

    const managementQueue = await apiRequest(
      managementToken,
      'GET',
      `/organizer/events/${fixture.eventId}/item-reservations`,
    );
    assert.ok([403, 404].includes(managementQueue.status), JSON.stringify(managementQueue.body));

    await logout(driver);
    await loginAsCmartManagement(driver);
    await driver.get(`${env.baseUrl}/admin#item-reservations`);
    await waitForTestId(driver, 'management-dashboard-root', 20000);
    const nav = await driver.findElements(By.css('[data-testid="workspace-nav-item-reservations"]'));
    assert.equal(nav.length, 0, 'CMart Management must not see Item Reservations nav');

    await logout(driver, { management: true });
    await loginAsOrganizer(driver);
    await openOrganizerReservationQueue(driver, fixture.eventId);
    const organizerNav = await driver.findElements(By.css('[data-testid="workspace-nav-item-reservations"]'));
    assert.ok(organizerNav.length > 0);

    const organizerDetail = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/item-reservations/${publicReference}`,
    );
    assert.equal(organizerDetail.status, 200);
    assert.ok(organizerDetail.body.reservation.reserving_user?.email);

    await logout(driver, { management: true });
    await loginOwnerVendor(driver, fixture);
    const vendorList = await apiRequest(vendorToken, 'GET', '/vendor/item-reservations');
    assert.equal(vendorList.status, 200);
    const serialized = JSON.stringify(vendorList.body).toLowerCase();
    assert.equal(serialized.includes('payment_proof'), false);
    assert.equal(serialized.includes('"invoice"'), false);

    await logout(driver);
    await loginUnrelatedVendor(driver, fixture);
    const unrelatedList = await apiRequest(unrelatedToken, 'GET', '/vendor/item-reservations');
    assert.equal(unrelatedList.status, 200);
    const unrelatedRows = unrelatedList.body.data || [];
    assert.equal(
      unrelatedRows.filter((row) => row.public_reference === publicReference).length,
      0,
    );

    await assertBookingUnchanged(organizerToken, fixture.vendorBookingId, bookingSnapshot);
  });
});
