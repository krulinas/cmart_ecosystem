import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsCommunityVendor } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import {
  VENDOR_WITHDRAWN_EXPECTATION,
  goToMyBookings,
  openVendorBookingDetails,
  vendorStatusMatches,
  withdrawVendorBooking,
} from '../helpers/vendor-bookings.js';
import { createPaidWithdrawalFixture, cleanupSiteFixtures } from '../helpers/site-fixtures.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

const MARKER = 'E2E-SITE-FIX';
const NO_REFUND_WITHDRAWAL_WARNING_MS =
  'Anda boleh menarik diri selepas bayaran dibuat, tetapi bayaran tidak akan dipulangkan. Tapak yang telah ditempah akan dibuka semula kepada vendor lain.';

describe('Vendor paid booking withdrawal', function () {
  let driver;
  let fixtures;

  before(async function () {
    this.timeout(90000);
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(function () {
    this.timeout(90000);
    fixtures = createPaidWithdrawalFixture();
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupSiteFixtures();
  });

  it('withdraws a paid confirmed booking with no-refund acknowledgement and releases sites', async function () {
    this.timeout(120000);

    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });

    await goToMyBookings(driver, env.baseUrl);
    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });

    const modal = await waitForTestId(driver, 'vendor-booking-details-modal');
    const modalText = await modal.getText();
    assert.match(modalText, /Paid/i, 'Booking details should show paid payment status.');
    for (const label of fixtures.site_labels) {
      assert.ok(modalText.includes(label), `Expected site label ${label} in booking details.`);
    }
    assert.match(modalText, /Confirmed/i, 'Allocation should be confirmed before withdrawal.');

    await driver.executeScript(
      `const button = document.querySelector('[data-testid="vendor-booking-action-withdraw"]');
       if (!button) throw new Error('Withdraw action missing.');
       button.click();`,
    );
    await waitForTestId(driver, 'withdraw-booking-modal');

    const warning = await waitForTestId(driver, 'withdraw-booking-warning');
    await driver.wait(
      async () => (await warning.getText()).includes('menarik diri'),
      10000,
      'Paid withdrawal warning should be visible.',
    );
    assert.equal((await warning.getText()).trim(), NO_REFUND_WITHDRAWAL_WARNING_MS);

    await driver.executeScript(
      `document.querySelector('[data-testid="withdraw-booking-cancel"]')?.click();`,
    );
    await waitForTestIdHidden(driver, 'withdraw-booking-modal', 10000);

    const statusBefore = await driver.findElement(
      By.css(`[data-testid="booking-list-item"][data-booking-id="${fixtures.booking_id}"]`),
    );
    const statusAttrBefore = await statusBefore.getAttribute('data-booking-status');
    assert.notEqual(statusAttrBefore, 'Withdrawn', 'Cancel must not mutate booking status.');

    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });
    const withdrawn = await withdrawVendorBooking(driver, MARKER, 'E2E paid withdrawal confirm', {
      bookingId: fixtures.booking_id,
    });

    assert.ok(
      vendorStatusMatches(withdrawn.statusAttr, withdrawn.statusLabel, VENDOR_WITHDRAWN_EXPECTATION),
      `Booking #${fixtures.booking_id} should be Withdrawn after confirmation.`,
    );

    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });
    const withdrawnModal = await waitForTestId(driver, 'vendor-booking-details-modal');
    const withdrawnText = await withdrawnModal.getText();
    assert.match(withdrawnText, /Withdrawn/i);
    assert.match(withdrawnText, /Released/i);
    assert.match(withdrawnText, /No refund was issued/i);
    assert.match(withdrawnText, /Paid/i);

    const withdrawButtons = await withdrawnModal.findElements(By.css('[data-testid="vendor-booking-action-withdraw"]'));
    assert.equal(withdrawButtons.length, 0, 'Withdraw action must disappear after withdrawal.');

    const login = await fetch(`${env.apiBaseUrl}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: fixtures.vendor_email, password: fixtures.vendor_password }),
    });
    const token = (await login.json()).token;
    const availability = await fetch(
      `${env.apiBaseUrl}/vendor/events/${fixtures.event_id}/site-availability`,
      { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
    );
    const sites = (await availability.json()).sites;
    const releasedSite = sites.find((site) => fixtures.site_ids.includes(site.id));
    assert.equal(releasedSite.availability_status, 'available');
  });
});
