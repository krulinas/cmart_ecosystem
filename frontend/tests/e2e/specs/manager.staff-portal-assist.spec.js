import { strict as assert } from 'node:assert';
import { By, until } from 'selenium-webdriver';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { loginAsManager, loginAsStaff, logout } from '../helpers/auth.js';
import { ensureE2EBookingExists } from '../helpers/booking.js';
import { createDriver } from '../helpers/driver.js';
import { findE2EBookingRow, openStaffBookings } from '../helpers/staff-bookings.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

async function openStaffPortalAssist(driver) {
  const button = await waitForTestId(driver, 'staff-portal-assist-toggle');
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', button);
  await driver.executeScript('arguments[0].click();', button);
  const banner = await waitForTestId(driver, 'staff-portal-assist-banner', 15000);
  await driver.wait(until.elementIsVisible(banner), 15000, 'Staff Portal Assist banner was not visible.');
}

async function returnToManagerPortal(driver) {
  const button = await waitForTestId(driver, 'staff-portal-assist-toggle');
  await driver.executeScript('arguments[0].click();', button);
  await waitForTestIdHidden(driver, 'staff-portal-assist-banner', 15000);
}

async function activeQueueRowsForBooking(driver, bookingId) {
  return driver.findElements(
    By.css(`[data-testid="staff-booking-row"][data-booking-section="queue"][data-booking-id="${bookingId}"]`),
  );
}

async function waitForActiveQueueMatch(driver, bookingId, marker) {
  return driver.wait(
    async () => {
      const rows = await activeQueueRowsForBooking(driver, bookingId);
      for (const row of rows) {
        try {
          const text = (await row.getText()).toLowerCase();
          if (text.includes(marker.toLowerCase())) {
            return row;
          }
        } catch (error) {
          if (error.name !== 'StaleElementReferenceError') {
            throw error;
          }
        }
      }
      return null;
    },
    20000,
    `Booking #${bookingId} did not appear in the Staff Portal Assist active queue.`,
  );
}

describe('Manager Staff Portal Assist', function () {
  this.timeout(240000);

  let driver;

  before(async function () {
    requireVendorCredentials();
    requireStaffCredentials();
    requireManagerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Manager can open Staff Portal Assist Mode and see the same Pending_Staff queue as staff', async function () {
    const marker = uniqueTestMarker('E2E-STAFF-PORTAL-ASSIST');
    const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
    await logout(driver);

    await loginAsStaff(driver);
    await openStaffBookings(driver, env.baseUrl);

    const staffMatch = await findE2EBookingRow(driver, ensured.marker, {
      preferActionTestId: 'staff-booking-action-forward',
    });
    const bookingId = staffMatch.bookingId;

    assert.equal(staffMatch.section, 'queue', `Staff should see booking #${bookingId} in the staff queue.`);
    assert.equal(staffMatch.status, 'Pending_Staff');
    assert.equal(staffMatch.hasForwardAction, true);
    assert.equal(staffMatch.hasApproveAction, false);

    await logout(driver, { management: true });
    await loginAsManager(driver);
    await openStaffBookings(driver, env.baseUrl);
    await waitForTestId(driver, 'staff-dashboard-root');

    assert.equal(
      (await activeQueueRowsForBooking(driver, bookingId)).length,
      0,
      `Manager normal view should not use booking #${bookingId} as an active staff queue row.`,
    );

    await openStaffPortalAssist(driver);
    const assistQueueRow = await waitForActiveQueueMatch(driver, bookingId, ensured.marker);

    const assistMatch = await findE2EBookingRow(driver, ensured.marker, {
      preferActionTestId: 'staff-booking-action-forward',
      bookingId,
    });

    assert.equal(await assistQueueRow.getAttribute('data-booking-status'), 'Pending_Staff');
    assert.equal(
      assistMatch.section,
      'queue',
      `Staff Portal Assist should show booking #${bookingId} in the active queue.`,
    );
    assert.equal(assistMatch.status, staffMatch.status);
    assert.equal(assistMatch.hasForwardAction, true);
    assert.equal(assistMatch.hasApproveAction, false);

    const assistDeleteButtons = await driver.findElements(
      By.css(`[data-testid="staff-booking-action-delete"][data-booking-id="${bookingId}"]`),
    );
    assert.equal(assistDeleteButtons.length, 0, 'Staff Portal Assist must not expose manager-only Delete.');

    // Manager assists Tier 1 review: Forward must succeed without a 422 error.
    const forwardButton = await driver.findElement(
      By.css(`[data-testid="staff-booking-action-forward"][data-booking-id="${bookingId}"]`),
    );
    await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', forwardButton);
    await driver.executeScript('arguments[0].click();', forwardButton);

    await driver.wait(
      async () => (await activeQueueRowsForBooking(driver, bookingId)).length === 0,
      20000,
      `Booking #${bookingId} should leave the assist staff queue after Forward.`,
    );

    const errorToasts = await driver.findElements(By.css('.Vue-Toastification__toast--error'));
    assert.equal(errorToasts.length, 0, 'Assist Forward must not raise an error toast (e.g. 422).');

    await returnToManagerPortal(driver);

    // Back in the Manager Portal, the forwarded booking must sit in the manager
    // approval queue (Pending_Boss) with the manager Approve action available.
    const managerQueueRow = await driver.wait(
      async () => {
        const rows = await activeQueueRowsForBooking(driver, bookingId);
        for (const row of rows) {
          try {
            if ((await row.getAttribute('data-booking-status')) === 'Pending_Boss') {
              return row;
            }
          } catch (error) {
            if (error.name !== 'StaleElementReferenceError') throw error;
          }
        }
        return null;
      },
      20000,
      `Booking #${bookingId} should appear in the manager approval queue after assist Forward.`,
    );

    const approveButtons = await managerQueueRow.findElements(
      By.css(`[data-testid="staff-booking-action-approve"][data-booking-id="${bookingId}"]`),
    );
    assert.ok(approveButtons.length > 0, 'Manager queue row must expose the Approve action after returning.');

    await logout(driver, { management: true });
  });
});
