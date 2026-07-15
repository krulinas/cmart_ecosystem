/* global describe, before, beforeEach, afterEach, it */
import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env, requireOrganizerCredentials } from '../config/env.js';
import {
  loginAsCommunityVendor,
  loginAsOrganizer,
  logout,
  managementApiRequest,
} from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import {
  closeVendorBookingDetailsModal,
  goToMyBookings,
  openVendorBookingDetails,
  withdrawVendorBooking,
} from '../helpers/vendor-bookings.js';
import { openOrganizerBookings, searchOrganizerBookings } from '../helpers/organizer-bookings.js';
import {
  cleanupSiteFixtures,
  createPaymentSubmittedWithdrawalFixture,
} from '../helpers/site-fixtures.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

const MARKER = 'E2E-SITE-FIX';
const NO_REFUND_WARNING =
  'Anda boleh menarik diri selepas bayaran dibuat, tetapi bayaran tidak akan dipulangkan. Tapak yang telah ditempah akan dibuka semula kepada vendor lain.';

describe('Organizer withdrawal reconciliation', function () {
  let driver;
  let fixtures;

  before(async function () {
    this.timeout(90000);
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(function () {
    this.timeout(90000);
    fixtures = createPaymentSubmittedWithdrawalFixture();
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupSiteFixtures();
  });

  it('shows payment-submitted withdrawal and sanitized audit history to Organizer', async function () {
    this.timeout(180000);

    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });
    await goToMyBookings(driver, env.baseUrl);
    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });

    const vendorModal = await waitForTestId(driver, 'vendor-booking-details-modal');
    const initialText = await vendorModal.getText();
    assert.match(initialText, /Pending Verification/i);
    assert.match(initialText, /Reserved/i);
    for (const label of fixtures.site_labels) {
      assert.ok(initialText.includes(label), `Vendor detail should show site ${label}.`);
    }

    await driver.executeScript(
      `document.querySelector('[data-testid="vendor-booking-action-withdraw"]')?.click();`,
    );
    const warning = await waitForTestId(driver, 'withdraw-booking-warning');
    await driver.wait(
      async () => (await warning.getText()).includes('menarik diri'),
      10000,
      'Payment-submitted withdrawal warning should be visible.',
    );
    assert.equal((await warning.getText()).trim(), NO_REFUND_WARNING);
    const acknowledgement = await waitForTestId(driver, 'withdraw-no-refund-acknowledgement');
    assert.equal(await acknowledgement.isSelected(), false);
    const confirm = await waitForTestId(driver, 'withdraw-booking-confirm');
    assert.equal(await confirm.isEnabled(), false, 'Acknowledgement must be required.');

    await driver.executeScript(
      `document.querySelector('[data-testid="withdraw-booking-cancel"]')?.click();`,
    );
    await waitForTestIdHidden(driver, 'withdraw-booking-modal', 10000);
    await closeVendorBookingDetailsModal(driver);

    await withdrawVendorBooking(driver, MARKER, 'E2E payment-submitted reconciliation', {
      bookingId: fixtures.booking_id,
    });
    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });
    const withdrawnVendorModal = await waitForTestId(driver, 'vendor-booking-details-modal');
    const withdrawnText = await withdrawnVendorModal.getText();
    assert.match(withdrawnText, /Withdrawn/i);
    assert.match(withdrawnText, /Pending Verification/i);
    assert.match(withdrawnText, /Released/i);
    assert.match(withdrawnText, /No refund was issued/i);
    await closeVendorBookingDetailsModal(driver);

    const availability = await driver.executeScript(
      async (apiBase, eventId) => {
        const token = localStorage.getItem('carboot_cmart_token');
        const response = await fetch(`${apiBase}/vendor/events/${eventId}/site-availability`, {
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        });
        return response.json();
      },
      env.apiBaseUrl,
      fixtures.event_id,
    );
    const releasedSites = availability.sites.filter((site) => fixtures.site_ids.includes(site.id));
    assert.ok(releasedSites.every((site) => site.availability_status === 'available'));

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerBookings(driver, env.baseUrl);
    await searchOrganizerBookings(driver, MARKER);

    await driver.executeScript(
      `const row = document.querySelector('[data-testid="organizer-booking-row"][data-booking-id="${fixtures.booking_id}"][data-booking-section="registry"]');
       if (!row) throw new Error('Fixture booking row not found in Organizer registry.');
       row.querySelector('[data-testid="organizer-booking-view-details"]')?.click();`,
    );

    const reconciliation = await waitForTestId(driver, 'organizer-withdrawal-reconciliation');
    const reconciliationText = await reconciliation.getText();
    assert.match(reconciliationText, /Payment Submitted/i);
    assert.match(reconciliationText, /No-refund policy applied/i);
    assert.match(reconciliationText, new RegExp(`RM\\s*${fixtures.invoice_amount.toFixed(2)}`));
    assert.match(reconciliationText, /Payment proof[\s\S]*Submitted/i);
    assert.match(reconciliationText, /Released/i);
    for (const label of fixtures.site_labels.slice(0, 2)) {
      assert.ok(reconciliationText.includes(label), `Organizer detail should show released site ${label}.`);
    }

    const auditTimeline = await waitForTestId(driver, 'organizer-booking-audit-timeline');
    const withdrawalAudit = await auditTimeline.findElement(
      By.css('[data-testid="organizer-booking-audit-item"][data-audit-action="vendor_withdraw"]'),
    );
    const auditText = await withdrawalAudit.getText();
    assert.match(auditText, /Vendor withdrew booking/i);
    assert.match(auditText, /Approved\s*→\s*Withdrawn/i);
    assert.match(auditText, /No refund policy applied/i);
    assert.match(auditText, /E2E Site Fixture Vendor/i);

    const detailsModal = await waitForTestId(driver, 'organizer-booking-details-modal');
    const forbiddenButtons = await detailsModal.findElements(
      By.xpath('.//button[contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "refund") or contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "reassign") or contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "restore")]'),
    );
    assert.equal(forbiddenButtons.length, 0, 'Organizer detail must remain read-only.');

    const backend = await managementApiRequest(driver, 'GET', `/bookings/${fixtures.booking_id}`);
    assert.equal(backend.status, 200);
    const booking = JSON.parse(backend.body);
    assert.equal(booking.approval_status, 'Withdrawn');
    assert.equal(booking.invoice.payment_status, 'Pending Verification');
    assert.equal(booking.invoice.amount, fixtures.invoice_amount.toFixed(2));
    assert.equal(booking.invoice.payment_proof_present, true);
    assert.equal(booking.withdrawal_reconciliation.allocation_status, 'released');
    assert.equal(booking.audit_timeline.filter((item) => item.action === 'vendor_withdraw').length, 1);
    assert.equal(JSON.stringify(booking).includes(fixtures.payment_proof_marker), false);
    assert.equal(JSON.stringify(booking).includes('active_lock'), false);
  });
});
