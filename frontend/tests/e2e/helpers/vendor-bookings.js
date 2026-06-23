import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { fillInputValue } from './booking.js';
import { waitForTestId } from './wait.js';

export const VENDOR_APPROVED_EXPECTATION = {
  attrs: new Set(['Approved']),
  labels: new Set(['Approved']),
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
