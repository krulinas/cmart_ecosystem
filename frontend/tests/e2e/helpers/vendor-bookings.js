import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { fillInputValue } from './booking.js';
import { waitForTestId, waitForTestIdHidden } from './wait.js';

export const VENDOR_APPROVED_EXPECTATION = {
  attrs: new Set(['Approved']),
  labels: new Set(['Approved']),
};

export const VENDOR_WITHDRAWN_EXPECTATION = {
  attrs: new Set(['Withdrawn']),
  labels: new Set(['Withdrawn']),
};

export function vendorStatusMatches(statusAttr, statusLabel, expectation) {
  return expectation.attrs.has(statusAttr) || expectation.labels.has(statusLabel);
}

export async function goToMyBookings(driver, baseUrl) {
  await driver.get(`${baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root');
  await waitForTestId(driver, 'my-bookings-root');

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
      return rows.length > 0;
    },
    20000,
    'My Bookings did not load any booking rows.',
  );
}

export async function searchVendorBookingByMarker(driver, marker) {
  await fillInputValue(driver, 'booking-search', marker);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
      for (const row of rows) {
        if (!(await row.isDisplayed())) continue;
        const text = (await row.getText()).toLowerCase();
        if (text.includes(marker.toLowerCase())) {
          return true;
        }
      }
      return false;
    },
    20000,
    `No vendor booking row matched marker "${marker}".`,
  );
}

export async function findVendorBookingByMarker(driver, marker, { bookingId } = {}) {
  await searchVendorBookingByMarker(driver, marker);

  const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
  const matches = [];

  for (const row of rows) {
    if (!(await row.isDisplayed())) continue;

    const text = (await row.getText()).toLowerCase();
    if (!text.includes(marker.toLowerCase())) continue;

    const rowBookingId = Number(await row.getAttribute('data-booking-id'));
    if (bookingId != null && rowBookingId !== bookingId) continue;

    matches.push({ row, bookingId: rowBookingId, text });
  }

  if (!matches.length) {
    throw new Error(
      `No E2E-marked vendor booking found for marker "${marker}"` +
        (bookingId != null ? ` (booking #${bookingId})` : '') +
        '. Only bookings containing the E2E marker are eligible for this test.',
    );
  }

  matches.sort((a, b) => b.bookingId - a.bookingId);
  return matches[0];
}

export async function readVendorBookingStatus(driver, marker, { bookingId } = {}) {
  const match = await findVendorBookingByMarker(driver, marker, { bookingId });
  const statusAttr = await match.row.getAttribute('data-booking-status');
  const statusLabel = (await match.row.findElement(By.css('[data-testid="booking-status"]')).getText()).trim();

  return {
    bookingId: match.bookingId,
    statusAttr,
    statusLabel,
    row: match.row,
  };
}

export async function waitForVendorBookingStatus(
  driver,
  marker,
  expectation,
  { bookingId, timeoutMs = 30000 } = {},
) {
  return driver.wait(
    async () => {
      const match = await findVendorBookingByMarker(driver, marker, { bookingId });
      const statusAttr = await match.row.getAttribute('data-booking-status');
      const statusLabel = (await match.row
        .findElement(By.css('[data-testid="booking-status"]'))
        .getText()).trim();

      if (vendorStatusMatches(statusAttr, statusLabel, expectation)) {
        return { bookingId: match.bookingId, statusAttr, statusLabel, rowText: match.text };
      }

      return null;
    },
    timeoutMs,
    `Vendor My Bookings did not show an expected status for marker "${marker}".`,
  );
}

export async function assertVendorBookingApproved(driver, marker, { bookingId, baseUrl, timeoutMs = 30000 } = {}) {
  if (baseUrl) {
    await goToMyBookings(driver, baseUrl);
  }

  const vendorView = await waitForVendorBookingStatus(driver, marker, VENDOR_APPROVED_EXPECTATION, {
    bookingId,
    timeoutMs,
  });

  assert.ok(
    vendorView.rowText.includes(marker.toLowerCase()),
    `Refusing to verify booking #${vendorView.bookingId} because the row does not contain the E2E marker.`,
  );

  assert.ok(
    vendorStatusMatches(vendorView.statusAttr, vendorView.statusLabel, VENDOR_APPROVED_EXPECTATION),
    `Booking #${vendorView.bookingId} shows "${vendorView.statusAttr || vendorView.statusLabel}" in My Bookings; expected Approved.`,
  );

  return vendorView;
}

export async function openVendorBookingDetails(driver, marker, { bookingId } = {}) {
  const match = await findVendorBookingByMarker(driver, marker, { bookingId });
  const resolvedBookingId = match.bookingId;

  await driver.wait(
    async () => {
      return driver.executeScript(
        `const row = document.querySelector('[data-testid="booking-list-item"][data-booking-id="${resolvedBookingId}"]');
         if (!row) return false;
         const button = row.querySelector('[data-testid="booking-view-details"]');
         if (!button) return false;
         button.scrollIntoView({ block: 'center' });
         button.click();
         return Boolean(
           document.querySelector('[data-testid="vendor-booking-details-overlay"]') ||
           document.querySelector('[data-testid="vendor-booking-details-modal"]'),
         );`,
      );
    },
    20000,
    `Vendor booking details did not open for booking #${resolvedBookingId} (marker "${marker}").`,
  );

  await driver.wait(
    async () => {
      const modal = await driver.findElement(By.css('[data-testid="vendor-booking-details-modal"]'));
      const text = await modal.getText();
      return !text.includes('Loading booking details');
    },
    20000,
    `Vendor booking details modal did not finish loading for marker "${marker}".`,
  );

  return resolvedBookingId;
}

export async function closeVendorBookingDetailsModal(driver) {
  const modal = await driver.findElement(By.css('[data-testid="vendor-booking-details-modal"]'));
  const closeButton = await modal.findElement(By.css('[aria-label="Close booking details"]'));
  await closeButton.click();
  await waitForTestIdHidden(driver, 'vendor-booking-details-modal', 10000);
}

async function refreshVendorMyBookingsList(driver, baseUrl = env.baseUrl) {
  await driver.get(`${baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root');
  await waitForTestId(driver, 'my-bookings-root');

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
      return rows.length > 0;
    },
    20000,
    'My Bookings did not load any booking rows after refresh.',
  );
}

async function waitForVendorBookingStatusWithRetry(
  driver,
  marker,
  expectation,
  { bookingId, timeoutMs = 45000 } = {},
) {
  try {
    return await waitForVendorBookingStatus(driver, marker, expectation, { bookingId, timeoutMs });
  } catch {
    await refreshVendorMyBookingsList(driver);
    return waitForVendorBookingStatus(driver, marker, expectation, {
      bookingId,
      timeoutMs: 20000,
    });
  }
}

export async function withdrawVendorBooking(driver, marker, reason, { bookingId } = {}) {
  const resolvedBookingId = await openVendorBookingDetails(driver, marker, { bookingId });

  await driver.wait(
    async () => {
      const buttons = await driver.findElements(By.css('[data-testid="vendor-booking-action-withdraw"]'));
      for (const button of buttons) {
        try {
          if (await button.isDisplayed()) return true;
        } catch (error) {
          if (error.name !== 'StaleElementReferenceError') throw error;
        }
      }
      return false;
    },
    20000,
    `Withdraw Booking action was not available for booking #${resolvedBookingId}.`,
  );

  await driver.wait(
    async () => {
      return driver.executeScript(
        `const button = document.querySelector('[data-testid="vendor-booking-action-withdraw"]');
         if (!button) return false;
         button.scrollIntoView({ block: 'center' });
         button.click();
         return Boolean(document.querySelector('[data-testid="withdraw-booking-modal"]'));`,
      );
    },
    20000,
    `Withdraw confirmation modal did not open for booking #${resolvedBookingId}.`,
  );

  await fillInputValue(driver, 'withdrawal-reason', reason);

  await driver.executeScript(
    `const button = document.querySelector('[data-testid="withdraw-booking-modal"] [data-testid="withdraw-booking-confirm"]');
     if (!button) throw new Error('Withdraw confirm button not found.');
     button.scrollIntoView({ block: 'center' });
     button.click();`,
  );

  await driver.wait(
    async () => {
      const modals = await driver.findElements(By.css('[data-testid="withdraw-booking-modal"]'));
      if (!modals.length) return true;

      for (const modal of modals) {
        try {
          if (!(await modal.isDisplayed())) continue;

          const text = await modal.getText();
          if (/unable to withdraw|can no longer be withdrawn|paid bookings cannot/i.test(text)) {
            throw new Error(
              `Withdraw API rejected for booking #${resolvedBookingId}: ${text.replace(/\s+/g, ' ').trim().slice(0, 240)}`,
            );
          }

          return false;
        } catch (error) {
          if (error.message?.includes('Withdraw API rejected')) throw error;
          if (error.name === 'StaleElementReferenceException' || error.name === 'StaleElementReferenceError') {
            return true;
          }
          throw error;
        }
      }

      return true;
    },
    30000,
    `Withdraw confirmation did not complete for booking #${resolvedBookingId}.`,
  );

  await refreshVendorMyBookingsList(driver);

  return waitForVendorBookingStatusWithRetry(driver, marker, VENDOR_WITHDRAWN_EXPECTATION, {
    bookingId: resolvedBookingId,
  });
}

export async function assertVendorBookingWithdrawn(driver, marker, { bookingId, baseUrl, timeoutMs = 30000 } = {}) {
  if (baseUrl) {
    await goToMyBookings(driver, baseUrl);
  }

  const vendorView = await waitForVendorBookingStatus(driver, marker, VENDOR_WITHDRAWN_EXPECTATION, {
    bookingId,
    timeoutMs,
  });

  assert.ok(
    vendorView.rowText.includes(marker.toLowerCase()),
    `Refusing to verify booking #${vendorView.bookingId} because the row does not contain the E2E marker.`,
  );

  assert.ok(
    vendorStatusMatches(vendorView.statusAttr, vendorView.statusLabel, VENDOR_WITHDRAWN_EXPECTATION),
    `Booking #${vendorView.bookingId} shows "${vendorView.statusAttr || vendorView.statusLabel}" in My Bookings; expected Withdrawn.`,
  );

  await openVendorBookingDetails(driver, marker, { bookingId: vendorView.bookingId });

  const withdrawButtons = await driver.findElements(By.css('[data-testid="vendor-booking-action-withdraw"]'));
  let visibleWithdrawAction = false;

  for (const button of withdrawButtons) {
    try {
      if (await button.isDisplayed()) {
        visibleWithdrawAction = true;
        break;
      }
    } catch (error) {
      if (error.name !== 'StaleElementReferenceError') throw error;
    }
  }

  assert.equal(
    visibleWithdrawAction,
    false,
    `Withdraw action should not be visible for withdrawn booking #${vendorView.bookingId}.`,
  );

  try {
    await closeVendorBookingDetailsModal(driver);
  } catch {
    await refreshVendorMyBookingsList(driver, baseUrl);
  }

  return vendorView;
}
