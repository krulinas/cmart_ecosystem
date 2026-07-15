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
} from '../helpers/vendor-bookings.js';
import { openOrganizerBookings, searchOrganizerBookings } from '../helpers/organizer-bookings.js';
import {
  attendanceFixtureStatus,
  cleanupSiteFixtures,
  createPaidThreeDayAttendanceFixture,
} from '../helpers/site-fixtures.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

const MARKER = 'E2E-SITE-FIX';
const REASON = 'Emergency family commitment on the final event day.';
const WARNING =
  'Pengecualian hari tidak mengubah jumlah bayaran. Tiada bayaran balik akan diberikan bagi hari yang dilepaskan.';

describe('Organizer attendance exception', function () {
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
    fixtures = createPaidThreeDayAttendanceFixture();
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupSiteFixtures();
  });

  it('reduces a paid three-day booking while preserving sites and finances', async function () {
    this.timeout(240000);

    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });
    await goToMyBookings(driver, env.baseUrl);
    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });
    const vendorBaseline = await waitForTestId(driver, 'vendor-booking-details-modal');
    const baselineText = await vendorBaseline.getText();
    assert.match(baselineText, /Paid/i);
    assert.match(baselineText, /This booking covers all active event days/i);
    for (const label of ['A01', 'A02']) assert.ok(baselineText.includes(label));
    for (const dayId of fixtures.day_ids) {
      assert.ok(dayId, 'Fixture EventDay IDs must be dynamic and present.');
    }
    assert.equal((await vendorBaseline.findElements(By.css('[data-testid="attendance-event-day-option"]'))).length, 0);
    await closeVendorBookingDetailsModal(driver);

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerBookings(driver, env.baseUrl);
    await searchOrganizerBookings(driver, MARKER);
    await driver.executeScript(
      `const row = document.querySelector('[data-testid="organizer-booking-row"][data-booking-id="${fixtures.booking_id}"][data-booking-section="registry"]');
       if (!row) throw new Error('Attendance fixture row not found.');
       row.querySelector('[data-testid="organizer-booking-view-details"]')?.click();`,
    );

    const policy = await waitForTestId(driver, 'organizer-attendance-policy');
    const policyText = await policy.getText();
    assert.match(policyText, /3 retained · 0 released/i);
    assert.match(policyText, /A01, A02/);
    assert.match(policyText, /Paid/i);
    await (await waitForTestId(driver, 'organizer-apply-attendance-exception')).click();

    let exceptionModal = await waitForTestId(driver, 'organizer-attendance-exception-modal');
    let options = await exceptionModal.findElements(By.css('[data-testid="attendance-event-day-option"]'));
    assert.equal(options.length, 3);
    for (const option of options) {
      const text = await option.getText();
      assert.match(text, /\d{4}/, 'Each EventDay must display a real date.');
    }
    const dayThreeInput = await options[2].findElement(By.css('input[type="checkbox"]'));
    assert.equal(await dayThreeInput.isSelected(), true);
    await dayThreeInput.click();
    await (await waitForTestId(driver, 'attendance-exception-reason')).sendKeys(REASON);
    const warning = await waitForTestId(driver, 'attendance-no-refund-warning');
    assert.ok((await warning.getText()).includes(WARNING));
    const confirm = await waitForTestId(driver, 'attendance-exception-confirm');
    assert.equal(await confirm.isEnabled(), false);

    const cancelButtons = await exceptionModal.findElements(By.xpath('.//button[normalize-space(.)="Cancel"]'));
    await cancelButtons[0].click();
    await waitForTestIdHidden(driver, 'organizer-attendance-exception-modal');
    let status = attendanceFixtureStatus();
    assert.equal(status.allocation_count, 6);
    assert.equal(status.active_count, 6);
    assert.equal(status.exception_count, 0);
    assert.equal(status.audit_count, 0);

    await (await waitForTestId(driver, 'organizer-apply-attendance-exception')).click();
    exceptionModal = await waitForTestId(driver, 'organizer-attendance-exception-modal');
    options = await exceptionModal.findElements(By.css('[data-testid="attendance-event-day-option"]'));
    await (await options[2].findElement(By.css('input[type="checkbox"]'))).click();
    await (await waitForTestId(driver, 'attendance-exception-reason')).sendKeys(REASON);
    await (await waitForTestId(driver, 'attendance-no-refund-acknowledgement')).click();
    const enabledConfirm = await waitForTestId(driver, 'attendance-exception-confirm');
    assert.equal(await enabledConfirm.isEnabled(), true);
    await enabledConfirm.click();
    await waitForTestIdHidden(driver, 'organizer-attendance-exception-modal', 15000);

    await driver.wait(
      async () => (await policy.getText()).includes('2 retained · 1 released'),
      15000,
      'Organizer policy should refresh from the authoritative response.',
    );
    const organizerResult = await policy.getText();
    assert.match(organizerResult, /2 retained · 1 released/i);
    assert.match(organizerResult, /Invoice amount unchanged · No refund/i);
    assert.match(organizerResult, new RegExp(REASON.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));

    const auditTimeline = await waitForTestId(driver, 'organizer-booking-audit-timeline');
    const attendanceAudit = await auditTimeline.findElement(
      By.css('[data-testid="organizer-booking-audit-item"][data-audit-action="organizer_applied_attendance_exception"]'),
    );
    assert.match(await attendanceAudit.getText(), /Organizer applied attendance exception/i);

    const backend = await managementApiRequest(driver, 'GET', `/bookings/${fixtures.booking_id}`);
    assert.equal(backend.status, 200);
    const booking = JSON.parse(backend.body);
    assert.equal(booking.approval_status, 'Approved');
    assert.equal(booking.invoice.payment_status, 'Paid');
    assert.equal(booking.invoice.amount, fixtures.invoice_amount.toFixed(2));
    assert.equal(booking.invoice.payment_proof_present, true);
    assert.equal(booking.attendance_policy.retained_event_day_count, 2);
    assert.equal(booking.attendance_policy.released_event_day_count, 1);
    assert.deepEqual(booking.attendance_policy.site_labels, ['A01', 'A02']);
    assert.equal(booking.audit_timeline.filter((item) =>
      item.action === 'organizer_applied_attendance_exception').length, 1);

    status = attendanceFixtureStatus();
    assert.equal(status.approval_status, 'Approved');
    assert.equal(status.payment_status, 'Paid');
    assert.equal(status.invoice_amount, fixtures.invoice_amount);
    assert.equal(status.payment_proof_present, true);
    assert.equal(status.allocation_count, 6);
    assert.equal(status.confirmed_count, 4);
    assert.equal(status.active_count, 4);
    assert.equal(status.released_count, 2);
    assert.equal(status.released_lock_null_count, 2);
    assert.equal(status.attendance_release_reason_count, 2);
    assert.equal(status.released_by_ids.length, 1);
    assert.equal(status.exception_count, 1);
    assert.equal(status.audit_count, 1);

    const organizerDetails = await waitForTestId(driver, 'organizer-booking-details-modal');
    const closeOrganizerDetails = await organizerDetails.findElement(
      By.css('button[aria-label="Close reconciliation"]'),
    );
    await driver.executeScript('arguments[0].click();', closeOrganizerDetails);
    await logout(driver);
    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });
    await goToMyBookings(driver, env.baseUrl);
    await openVendorBookingDetails(driver, MARKER, { bookingId: fixtures.booking_id });
    const vendorResult = await waitForTestId(driver, 'vendor-booking-details-modal');
    const vendorText = await vendorResult.getText();
    assert.match(vendorText, /Attendance exception approved by Organizer/i);
    assert.match(vendorText, /Retained EventDays/i);
    assert.match(vendorText, /Released EventDays/i);
    assert.ok(vendorText.includes(REASON));
    assert.match(vendorText, /Paid/i);
    assert.match(vendorText, /No refund applies to released EventDays/i);
    assert.ok(vendorText.includes('A01') && vendorText.includes('A02'));
    assert.equal((await vendorResult.findElements(By.css('[data-testid="attendance-event-day-option"]'))).length, 0);
    assert.equal((await vendorResult.findElements(By.css('[data-testid="organizer-apply-attendance-exception"]'))).length, 0);
  });
});
