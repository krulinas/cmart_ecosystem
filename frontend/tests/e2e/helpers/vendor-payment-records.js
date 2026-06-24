import { strict as assert } from 'node:assert';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { fillInputValue } from './booking.js';
import { findVendorBookingByMarker } from './vendor-bookings.js';
import { waitForTestId, waitForTestIdHidden } from './wait.js';

const __dirname = dirname(fileURLToPath(import.meta.url));

export const PAYMENT_PROOF_FIXTURE = resolve(__dirname, '../fixtures/payment-proof.png');

export const VENDOR_PENDING_VERIFICATION_STATUS = 'Pending Verification';

const ACCEPTABLE_PAYMENT_STATUSES = new Set([
  'Unpaid',
  'Paid',
  'Pending',
  'Pending Verification',
  'Not Issued',
]);

export async function goToVendorPaymentRecords(driver, baseUrl = env.baseUrl) {
  await driver.get(`${baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root');

  const receiptsRoot = await waitForTestId(driver, 'vendor-history-receipts-root', 20000);
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', receiptsRoot);

  await waitForTestIdHidden(driver, 'vendor-receipts-loading', 20000);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="receipt-list-item"]'));
      if (rows.length > 0) return true;

      const emptyStates = await driver.findElements(
        By.xpath(
          "//*[@data-testid='vendor-history-receipts-root']//*[contains(normalize-space(.), 'No payment records')]",
        ),
      );

      for (const element of emptyStates) {
        if (await element.isDisplayed()) return true;
      }

      const errorStates = await driver.findElements(
        By.xpath(
          "//*[@data-testid='vendor-history-receipts-root']//*[contains(normalize-space(.), 'Unable to load your payment records')]",
        ),
      );

      for (const element of errorStates) {
        if (await element.isDisplayed()) return true;
      }

      return false;
    },
    20000,
    'Vendor payment records section did not finish loading.',
  );
}

export async function searchVendorPaymentRecord(driver, searchText) {
  await fillInputValue(driver, 'receipt-search', searchText);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="receipt-list-item"]'));
      for (const row of rows) {
        if (!(await row.isDisplayed())) continue;
        const text = (await row.getText()).toLowerCase();
        if (text.includes(String(searchText).toLowerCase())) {
          return true;
        }
      }
      return false;
    },
    20000,
    `No payment record row matched search text "${searchText}".`,
  );
}

async function expandAllPaymentRecordsIfNeeded(driver) {
  const viewAllButtons = await driver.findElements(
    By.xpath("//button[contains(normalize-space(.), 'View All Payment Records')]"),
  );

  if (viewAllButtons.length && (await viewAllButtons[0].isDisplayed())) {
    await viewAllButtons[0].click();
  }
}

export async function findVendorPaymentRecord(driver, searchText, { bookingId } = {}) {
  await searchVendorPaymentRecord(driver, searchText);
  await expandAllPaymentRecordsIfNeeded(driver);

  const rows = await driver.findElements(By.css('[data-testid="receipt-list-item"]'));
  const matches = [];

  for (const row of rows) {
    if (!(await row.isDisplayed())) continue;

    const text = (await row.getText()).toLowerCase();
    if (!text.includes(String(searchText).toLowerCase())) continue;

    const rowBookingId = Number(await row.getAttribute('data-booking-id'));
    if (bookingId != null && rowBookingId !== bookingId) continue;

    matches.push({ row, bookingId: rowBookingId, text });
  }

  if (!matches.length) {
    throw new Error(
      `No E2E-linked payment record found for "${searchText}"` +
        (bookingId != null ? ` (booking #${bookingId})` : '') +
        '. Only records matching the verified booking reference are eligible for this test.',
    );
  }

  matches.sort((a, b) => b.bookingId - a.bookingId);
  return matches[0];
}

export async function waitForVendorPaymentRecord(driver, searchText, { bookingId, timeoutMs = 30000 } = {}) {
  return driver.wait(
    async () => {
      try {
        const match = await findVendorPaymentRecord(driver, searchText, { bookingId });
        return match;
      } catch {
        return null;
      }
    },
    timeoutMs,
    `Vendor payment records did not show an expected row for "${searchText}".`,
  );
}

export async function assertVendorPaymentRecordVisible(
  driver,
  marker,
  { bookingId, baseUrl, timeoutMs = 30000 } = {},
) {
  const bookingMatch = await findVendorBookingByMarker(driver, marker, { bookingId });
  const resolvedBookingId = bookingMatch.bookingId;

  assert.ok(
    bookingMatch.text.includes(marker.toLowerCase()),
    `Refusing to verify payment record for booking #${resolvedBookingId} because My Bookings does not contain the E2E marker.`,
  );

  if (baseUrl) {
    await goToVendorPaymentRecords(driver, baseUrl);
  }

  const searchTerms = [`#${resolvedBookingId}`, String(resolvedBookingId)];
  if (env.bookingEventName) {
    searchTerms.push(env.bookingEventName);
  }

  let recordMatch;
  let lastError;

  for (const term of searchTerms) {
    try {
      recordMatch = await waitForVendorPaymentRecord(driver, term, {
        bookingId: resolvedBookingId,
        timeoutMs,
      });
      break;
    } catch (error) {
      lastError = error;
      await fillInputValue(driver, 'receipt-search', '');
    }
  }

  if (!recordMatch) {
    throw lastError || new Error(`Could not locate payment record for booking #${resolvedBookingId}.`);
  }

  const eventLabel = (
    await recordMatch.row.findElement(By.css('[data-testid="receipt-event-label"]')).getText()
  ).trim();
  const amountText = (
    await recordMatch.row.findElement(By.css('[data-testid="receipt-amount"]')).getText()
  ).trim();
  const paymentStatus = (
    await recordMatch.row.findElement(By.css('[data-testid="receipt-payment-status"]')).getText()
  ).trim();
  const boothLabel = (
    await recordMatch.row.findElement(By.css('[data-testid="receipt-booth-label"]')).getText()
  ).trim();

  assert.ok(eventLabel && eventLabel !== '—', `Payment record for booking #${resolvedBookingId} is missing an event label.`);
  assert.match(
    amountText,
    /RM\s+\d+\.\d{2}/,
    `Payment record for booking #${resolvedBookingId} should show a currency amount.`,
  );
  assert.ok(
    ACCEPTABLE_PAYMENT_STATUSES.has(paymentStatus),
    `Payment record for booking #${resolvedBookingId} shows unexpected status "${paymentStatus}".`,
  );
  assert.ok(
    recordMatch.text.includes(String(resolvedBookingId)),
    `Payment record row for booking #${resolvedBookingId} does not include the booking reference.`,
  );
  assert.ok(
    boothLabel && boothLabel !== '—',
    `Payment record for approved booking #${resolvedBookingId} should show booth/tapak information.`,
  );

  const viewInvoiceButtons = await recordMatch.row.findElements(
    By.xpath(".//button[contains(normalize-space(.), 'View Invoice')]"),
  );
  let invoiceActionVisible = false;

  for (const button of viewInvoiceButtons) {
    if (await button.isDisplayed()) {
      invoiceActionVisible = true;
      break;
    }
  }

  assert.ok(
    invoiceActionVisible,
    `Payment record for booking #${resolvedBookingId} should expose a View Invoice action after approval.`,
  );

  return {
    bookingId: resolvedBookingId,
    eventLabel,
    amountText,
    paymentStatus,
    boothLabel,
    rowText: recordMatch.text,
    row: recordMatch.row,
  };
}

async function refreshVendorPaymentRecordsList(driver, baseUrl = env.baseUrl) {
  await driver.get(`${baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root');
  const receiptsRoot = await waitForTestId(driver, 'vendor-history-receipts-root', 20000);
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', receiptsRoot);
  await waitForTestIdHidden(driver, 'vendor-receipts-loading', 20000);
}

export async function openVendorPaymentAction(driver, bookingId) {
  const searchText = String(bookingId);
  const recordMatch = await findVendorPaymentRecord(driver, searchText, { bookingId });

  await driver.wait(
    async () => {
      return driver.executeScript(
        `const row = document.querySelector('[data-testid="receipt-list-item"][data-booking-id="${bookingId}"]');
         if (!row) return false;
         const button = row.querySelector('[data-testid="payment-action-button"]');
         if (!button) return false;
         button.scrollIntoView({ block: 'center' });
         button.click();
         return Boolean(document.querySelector('[data-testid="invoice-payment-modal"]'));`,
      );
    },
    20000,
    `Submit Payment action did not open for booking #${bookingId}.`,
  );

  await waitForTestId(driver, 'invoice-payment-modal');
  return recordMatch;
}

export async function uploadVendorPaymentProof(driver, fixturePath = PAYMENT_PROOF_FIXTURE) {
  const fileInput = await waitForTestId(driver, 'payment-proof-input');
  await fileInput.sendKeys(fixturePath);
}

export async function submitVendorPayment(driver, { bookingId, timeoutMs = 30000 } = {}) {
  await driver.executeScript(
    `const button = document.querySelector('[data-testid="payment-submit-button"]');
     if (!button) throw new Error('Payment submit button not found.');
     button.scrollIntoView({ block: 'center' });
     button.click();`,
  );

  await driver.wait(
    async () => {
      const modals = await driver.findElements(By.css('[data-testid="invoice-payment-modal"]'));
      if (!modals.length) return true;

      for (const modal of modals) {
        try {
          if (!(await modal.isDisplayed())) continue;

          const text = await modal.getText();
          if (/unable to submit|already been submitted|payment has already/i.test(text)) {
            throw new Error(
              `Payment submission rejected${bookingId != null ? ` for booking #${bookingId}` : ''}: ${text.replace(/\s+/g, ' ').trim().slice(0, 240)}`,
            );
          }

          const successMessages = await modal.findElements(By.css('[data-testid="payment-success-message"]'));
          for (const message of successMessages) {
            if (await message.isDisplayed()) return true;
          }

          return false;
        } catch (error) {
          if (error.message?.includes('Payment submission rejected')) throw error;
          if (error.name === 'StaleElementReferenceError') return true;
          throw error;
        }
      }

      return true;
    },
    timeoutMs,
    `Payment submission did not complete${bookingId != null ? ` for booking #${bookingId}` : ''}.`,
  );

  try {
    await waitForTestIdHidden(driver, 'invoice-payment-modal', 10000);
  } catch {
    await refreshVendorPaymentRecordsList(driver);
  }
}

export async function readVendorPaymentStatus(driver, bookingId) {
  const recordMatch = await findVendorPaymentRecord(driver, String(bookingId), { bookingId });
  const paymentStatus = (
    await recordMatch.row.findElement(By.css('[data-testid="receipt-payment-status"]')).getText()
  ).trim();

  return {
    bookingId: recordMatch.bookingId,
    paymentStatus,
    rowText: recordMatch.text,
    row: recordMatch.row,
  };
}

export async function waitForVendorPaymentStatus(
  driver,
  bookingId,
  expectedStatus,
  { timeoutMs = 45000 } = {},
) {
  return driver.wait(
    async () => {
      try {
        const current = await readVendorPaymentStatus(driver, bookingId);
        if (current.paymentStatus === expectedStatus) {
          return current;
        }
      } catch {
        await refreshVendorPaymentRecordsList(driver);
        await fillInputValue(driver, 'receipt-search', String(bookingId));
      }

      return null;
    },
    timeoutMs,
    `Payment record for booking #${bookingId} did not reach status "${expectedStatus}".`,
  );
}

export const VENDOR_PAID_STATUS = 'Paid';

export async function waitForVendorPaidStatus(driver, bookingId, { baseUrl, timeoutMs = 45000 } = {}) {
  if (baseUrl) {
    await goToVendorPaymentRecords(driver, baseUrl);
  }

  return waitForVendorPaymentStatus(driver, bookingId, VENDOR_PAID_STATUS, { timeoutMs });
}

export async function goToVendorEventPasses(driver, baseUrl = env.baseUrl) {
  await driver.get(`${baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root');
  const root = await waitForTestId(driver, 'vendor-event-passes-root', 20000);
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', root);

  await driver.wait(
    async () => {
      const loadingBlocks = await driver.findElements(
        By.css('[data-testid="vendor-event-passes-root"] .animate-pulse'),
      );

      for (const block of loadingBlocks) {
        if (await block.isDisplayed()) return false;
      }

      const emptyState = await driver.findElements(
        By.xpath(
          "//*[@data-testid='vendor-event-passes-root']//*[contains(normalize-space(.), 'No active event pass yet')]",
        ),
      );

      for (const element of emptyState) {
        if (await element.isDisplayed()) return true;
      }

      const passButtons = await driver.findElements(By.css('[data-testid="vendor-pass-button"]'));
      if (passButtons.length) return true;

      const passCards = await driver.findElements(
        By.xpath("//*[@data-testid='vendor-event-passes-root']//*[contains(normalize-space(.), 'Booking #')]"),
      );

      return passCards.length > 0;
    },
    20000,
    'Vendor event passes section did not finish loading.',
  );
}

async function selectVendorPassByBookingId(driver, bookingId) {
  const selects = await driver.findElements(
    By.css('[data-testid="vendor-event-passes-root"] select'),
  );

  if (!selects.length) return;

  await driver.executeScript(
    (select, id) => {
      const option = Array.from(select.options).find((entry) => Number(entry.value) === Number(id));
      if (!option) return;
      select.value = option.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    },
    selects[0],
    bookingId,
  );
}

export async function openVendorPassOrReceipt(driver, bookingId) {
  await selectVendorPassByBookingId(driver, bookingId);

  await driver.wait(
    async () => {
      return driver.executeScript(
        (id) => {
          const scopedButton = document.querySelector(
            `[data-testid="vendor-pass-button"][data-booking-id="${id}"]`,
          );
          if (scopedButton) {
            scopedButton.scrollIntoView({ block: 'center' });
            scopedButton.click();
            return Boolean(document.querySelector('[data-testid="vendor-pass-modal"]'));
          }

          const root = document.querySelector('[data-testid="vendor-event-passes-root"]');
          if (!root) return false;

          const buttons = root.querySelectorAll('[data-testid="vendor-pass-button"]');
          for (const button of buttons) {
            const sectionText = root.textContent || '';
            if (!sectionText.includes(`#${id}`) && !sectionText.includes(String(id))) continue;
            button.scrollIntoView({ block: 'center' });
            button.click();
            return Boolean(document.querySelector('[data-testid="vendor-pass-modal"]'));
          }

          return false;
        },
        bookingId,
      );
    },
    30000,
    `Vendor pass modal did not open for booking #${bookingId}.`,
  );

  await waitForTestId(driver, 'vendor-pass-modal');
}

async function isElementVisible(element) {
  try {
    return await element.isDisplayed();
  } catch {
    return false;
  }
}

export async function assertVendorPassLocked(driver, bookingId, { baseUrl } = {}) {
  if (baseUrl) {
    await goToVendorEventPasses(driver, baseUrl);
  } else {
    await goToVendorEventPasses(driver);
  }

  await selectVendorPassByBookingId(driver, bookingId);

  const scopedButtons = await driver.findElements(
    By.css(`[data-testid="vendor-pass-button"][data-booking-id="${bookingId}"]`),
  );

  for (const button of scopedButtons) {
    assert.equal(
      await isElementVisible(button),
      false,
      `View Full Pass must stay locked for booking #${bookingId} until payment is verified as Paid.`,
    );
  }

  const allPassButtons = await driver.findElements(By.css('[data-testid="vendor-pass-button"]'));
  for (const button of allPassButtons) {
    if (!(await isElementVisible(button))) continue;

    const buttonBookingId = Number(await button.getAttribute('data-booking-id'));
    if (buttonBookingId === bookingId) {
      assert.fail(
        `View Full Pass must stay locked for booking #${bookingId} until payment is verified as Paid.`,
      );
    }
  }

  const modals = await driver.findElements(By.css('[data-testid="vendor-pass-modal"]'));
  for (const modal of modals) {
    assert.equal(
      await isElementVisible(modal),
      false,
      `Vendor pass modal must not be open while pass is locked for booking #${bookingId}.`,
    );
  }
}

export async function assertVendorReceiptAndPassLocked(
  driver,
  marker,
  {
    bookingId,
    baseUrl,
    expectedPaymentStatus = 'Unpaid',
    expectViewInvoice = true,
    timeoutMs = 45000,
  } = {},
) {
  const bookingMatch = await findVendorBookingByMarker(driver, marker, { bookingId });
  const resolvedBookingId = bookingMatch.bookingId;

  assert.ok(
    bookingMatch.text.includes(marker.toLowerCase()),
    `Refusing to verify locked receipt/pass for booking #${resolvedBookingId} because My Bookings does not contain the E2E marker.`,
  );

  if (baseUrl) {
    await goToVendorPaymentRecords(driver, baseUrl);
  }

  const paymentView = await waitForVendorPaymentStatus(driver, resolvedBookingId, expectedPaymentStatus, {
    timeoutMs,
  });

  assert.notEqual(
    paymentView.paymentStatus,
    VENDOR_PAID_STATUS,
    `Payment for booking #${resolvedBookingId} must not be Paid before staff verification.`,
  );

  const receiptButtons = await paymentView.row.findElements(By.css('[data-testid="view-receipt-button"]'));
  let receiptActionVisible = false;

  for (const button of receiptButtons) {
    if (await isElementVisible(button)) {
      receiptActionVisible = true;
      break;
    }
  }

  assert.equal(
    receiptActionVisible,
    false,
    `View Receipt must stay locked for booking #${resolvedBookingId} while payment status is "${expectedPaymentStatus}".`,
  );

  if (expectViewInvoice) {
    const invoiceButtons = await paymentView.row.findElements(By.css('[data-testid="view-invoice-button"]'));
    let invoiceActionVisible = false;

    for (const button of invoiceButtons) {
      if (await isElementVisible(button)) {
        invoiceActionVisible = true;
        break;
      }
    }

    assert.ok(
      invoiceActionVisible,
      `Approved booking #${resolvedBookingId} should still expose View Invoice before payment is verified as Paid.`,
    );
  }

  await assertVendorPassLocked(driver, resolvedBookingId, { baseUrl });

  return paymentView;
}

export async function assertVendorReceiptOrPassVisible(
  driver,
  marker,
  { bookingId, baseUrl, timeoutMs = 45000 } = {},
) {
  const bookingMatch = await findVendorBookingByMarker(driver, marker, { bookingId });
  const resolvedBookingId = bookingMatch.bookingId;

  assert.ok(
    bookingMatch.text.includes(marker.toLowerCase()),
    `Refusing to verify paid receipt/pass for booking #${resolvedBookingId} because My Bookings does not contain the E2E marker.`,
  );

  if (baseUrl) {
    await goToVendorPaymentRecords(driver, baseUrl);
  }

  const paidRecord = await waitForVendorPaidStatus(driver, resolvedBookingId, { timeoutMs });

  const receiptButtons = await paidRecord.row.findElements(By.css('[data-testid="view-receipt-button"]'));
  let receiptActionVisible = false;

  for (const button of receiptButtons) {
    if (await button.isDisplayed()) {
      receiptActionVisible = true;
      break;
    }
  }

  assert.ok(
    receiptActionVisible,
    `Paid payment record for booking #${resolvedBookingId} should expose View Receipt.`,
  );

  const invoiceButtons = await paidRecord.row.findElements(By.css('[data-testid="view-invoice-button"]'));
  let invoiceStillPrimary = false;

  for (const button of invoiceButtons) {
    if (await button.isDisplayed()) {
      invoiceStillPrimary = true;
      break;
    }
  }

  assert.equal(
    invoiceStillPrimary,
    false,
    `View Invoice should not remain the primary document action after payment is Paid for booking #${resolvedBookingId}.`,
  );

  await goToVendorEventPasses(driver, baseUrl);
  await selectVendorPassByBookingId(driver, resolvedBookingId);

  await driver.wait(
    async () => {
      const buttons = await driver.findElements(By.css('[data-testid="vendor-pass-button"]'));
      for (const button of buttons) {
        if (await button.isDisplayed()) return true;
      }
      return false;
    },
    timeoutMs,
    `Vendor event pass should unlock after Paid verification for booking #${resolvedBookingId}.`,
  );

  await openVendorPassOrReceipt(driver, resolvedBookingId);

  const bookingReference = await driver.wait(
    async () => {
      const elements = await driver.findElements(By.css('[data-testid="vendor-pass-booking-reference"]'));
      for (const element of elements) {
        if (!(await isElementVisible(element))) continue;
        const text = (await element.getText()).trim();
        if (text.includes(String(resolvedBookingId))) return text;
      }
      return null;
    },
    timeoutMs,
    `Vendor pass modal should show booking #${resolvedBookingId}.`,
  );

  const eventLabel = (
    await driver.findElement(By.css('[data-testid="vendor-pass-event-label"]')).getText()
  ).trim();
  const boothLabel = (
    await driver.findElement(By.css('[data-testid="vendor-pass-booth-label"]')).getText()
  ).trim();

  assert.ok(
    bookingReference.includes(String(resolvedBookingId)),
    `Vendor pass modal should show booking #${resolvedBookingId}.`,
  );
  assert.ok(eventLabel && eventLabel !== '—', `Vendor pass modal should show an event label for booking #${resolvedBookingId}.`);
  assert.ok(boothLabel && boothLabel !== '—', `Vendor pass modal should show booth/tapak for booking #${resolvedBookingId}.`);

  const paidBadges = await driver.findElements(By.css('[data-testid="vendor-pass-payment-status"]'));
  let paidBadgeVisible = false;

  for (const badge of paidBadges) {
    if (!(await badge.isDisplayed())) continue;
    const text = (await badge.getText()).trim();
    if (text === VENDOR_PAID_STATUS) {
      paidBadgeVisible = true;
      break;
    }
  }

  assert.ok(
    paidBadgeVisible,
    `Vendor pass modal should indicate Paid status for booking #${resolvedBookingId}.`,
  );

  return {
    bookingId: resolvedBookingId,
    paymentStatus: paidRecord.paymentStatus,
    bookingReference,
    eventLabel,
    boothLabel,
  };
}

export async function assertVendorPaymentSubmitted(
  driver,
  marker,
  {
    bookingId,
    expectedStatus = VENDOR_PENDING_VERIFICATION_STATUS,
    baseUrl,
    timeoutMs = 45000,
  } = {},
) {
  const bookingMatch = await findVendorBookingByMarker(driver, marker, { bookingId });
  const resolvedBookingId = bookingMatch.bookingId;

  assert.ok(
    bookingMatch.text.includes(marker.toLowerCase()),
    `Refusing to verify payment submission for booking #${resolvedBookingId} because My Bookings does not contain the E2E marker.`,
  );

  if (baseUrl) {
    await goToVendorPaymentRecords(driver, baseUrl);
  }

  const paymentView = await waitForVendorPaymentStatus(driver, resolvedBookingId, expectedStatus, {
    timeoutMs,
  });

  assert.equal(
    paymentView.bookingId,
    resolvedBookingId,
    'Payment record must remain linked to the same booking reference.',
  );
  assert.ok(
    paymentView.rowText.includes(String(resolvedBookingId)),
    `Payment record row for booking #${resolvedBookingId} must include the booking reference.`,
  );
  assert.ok(
    ACCEPTABLE_PAYMENT_STATUSES.has(paymentView.paymentStatus),
    `Unexpected payment status "${paymentView.paymentStatus}" for booking #${resolvedBookingId}.`,
  );

  const submitButtons = await paymentView.row.findElements(By.css('[data-testid="payment-action-button"]'));
  let submitStillVisible = false;

  for (const button of submitButtons) {
    if (await button.isDisplayed()) {
      submitStillVisible = true;
      break;
    }
  }

  assert.equal(
    submitStillVisible,
    false,
    `Submit Payment should not remain available after status "${expectedStatus}" for booking #${resolvedBookingId}.`,
  );

  return paymentView;
}
