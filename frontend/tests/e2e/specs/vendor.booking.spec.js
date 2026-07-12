import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsVendor } from '../helpers/auth.js';
import { uniqueTestMarker } from '../helpers/actions.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId, waitForTestIdHidden, waitForUrlContains } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

async function setSelectValue(driver, testId, value) {
  const selectElement = await waitForTestId(driver, testId);
  await driver.executeScript(
    `const select = arguments[0];
     select.value = arguments[1];
     select.dispatchEvent(new Event('input', { bubbles: true }));
     select.dispatchEvent(new Event('change', { bubbles: true }));`,
    selectElement,
    value,
  );
}

const NO_EVENTS_MESSAGE =
  'No available event found for E2E booking test. Please create an upcoming active event from staff dashboard first.';

const ACCEPTABLE_STATUSES = new Set([
  'Pending_Organizer',
  'Needs_Revision',
  'Approved',
]);

const ACCEPTABLE_STATUS_LABELS = new Set([
  'Pending Organizer Review',
  'Organizer Review',
  'Pending',
  'Needs Revision',
  'Approved',
]);

const PRODUCT_CATEGORIES = [
  'Pre-loved / Thrift',
  'Food & Beverages',
  'Clothing & Apparel',
  'Handicrafts & Art',
  'Electronics & Gadgets',
  'Others',
];

function resolveCategory(category) {
  const needle = String(category || '').toLowerCase().trim();
  if (!needle) return 'Food & Beverages';

  const exact = PRODUCT_CATEGORIES.find((option) => option.toLowerCase() === needle);
  if (exact) return exact;

  const partial = PRODUCT_CATEGORIES.find(
    (option) => option.toLowerCase().includes(needle) || needle.includes(option.toLowerCase()),
  );
  return partial || category;
}

async function readBookableEventOptions(driver) {
  const noEvents = await driver.findElements(By.css('[data-testid="booking-no-events"]'));
  if (noEvents.length && (await noEvents[0].isDisplayed())) {
    throw new Error(NO_EVENTS_MESSAGE);
  }

  const selectElements = await driver.findElements(By.css('[data-testid="booking-event-select"]'));
  if (!selectElements.length || !(await selectElements[0].isDisplayed())) {
    return [];
  }

  const options = await selectElements[0].findElements(By.css('option'));
  const bookableOptions = [];

  for (const option of options) {
    const value = await option.getAttribute('value');
    if (!value) continue;
    bookableOptions.push({
      id: value,
      text: (await option.getText()).trim(),
    });
  }

  return bookableOptions;
}

async function openBookableEvent(driver) {
  const bookableOptions = await readBookableEventOptions(driver);
  if (!bookableOptions.length) {
    throw new Error(NO_EVENTS_MESSAGE);
  }

  const preferred = env.bookingEventName
    ? bookableOptions.find((option) => option.text.includes(env.bookingEventName))
    : null;

  const chosen = preferred || bookableOptions[0];
  await driver.get(`${env.baseUrl}/vendor-booking?event_id=${chosen.id}`);
  await waitForTestIdHidden(driver, 'booking-events-loading', 20000);
  await waitForTestId(driver, 'booking-form');
  await waitForTestId(driver, 'booking-selected-event');

  const title = await driver
    .findElement(By.css('[data-testid="booking-selected-event"] h2'))
    .getText();

  return title.trim();
}

async function getToastTexts(driver) {
  const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
  const texts = [];

  for (const toast of toasts) {
    texts.push((await toast.getText()).trim());
  }

  return texts.filter(Boolean);
}

async function waitForSubmitOutcome(driver, toastsBeforeSubmit = [], timeoutMs = 25000) {
  const ignored = new Set(toastsBeforeSubmit);

  return driver.wait(
    async () => {
      const currentUrl = await driver.getCurrentUrl();
      if (currentUrl.includes('/dashboard')) {
        return { type: 'success' };
      }

      const newToasts = (await getToastTexts(driver)).filter((text) => !ignored.has(text));
      for (const text of newToasts) {
        if (/booking submitted|201 created|successfully submitted|awaiting tier/i.test(text)) {
          return { type: 'success', message: text };
        }

        if (/already|duplicate|existing/i.test(text)) {
          return { type: 'duplicate', message: text };
        }

        if (/error|unable|unprocessable|422|500|invalid/i.test(text)) {
          return { type: 'error', message: text };
        }
      }

      return null;
    },
    timeoutMs,
    'Booking submit did not redirect to the dashboard or show a booking toast response.',
  );
}

async function fillInputValue(driver, testId, value) {
  const input = await waitForTestId(driver, testId);
  await driver.executeScript(
    `const field = arguments[0];
     field.value = arguments[1];
     field.dispatchEvent(new Event('input', { bubbles: true }));
     field.dispatchEvent(new Event('change', { bubbles: true }));`,
    input,
    value,
  );
}

async function fillBookingForm(driver, detailsMarker) {
  await fillInputValue(driver, 'booking-business-name', env.bookingBusinessName);
  await setSelectValue(driver, 'booking-category', resolveCategory(env.bookingCategory));
  await fillInputValue(driver, 'booking-details', detailsMarker);

  await driver.wait(
    async () => {
      const submitButton = await driver.findElement(By.css('[data-testid="booking-submit"]'));
      return submitButton.isEnabled();
    },
    10000,
    'Booking submit stayed disabled. Confirm the event is selected and required fields are filled.',
  );
}

async function submitBookingForm(driver) {
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="booking-form"]');
    if (!form) throw new Error('Booking form not found.');
    form.requestSubmit();
  `);
}

async function expandAllBookingsIfNeeded(driver) {
  const viewAllButtons = await driver.findElements(
    By.xpath("//button[contains(normalize-space(.), 'View All Bookings')]"),
  );

  if (viewAllButtons.length && (await viewAllButtons[0].isDisplayed())) {
    await viewAllButtons[0].click();
  }
}

async function assertBookingVisible(driver, searchText) {
  await waitForTestId(driver, 'my-bookings-root');

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
      return rows.length > 0;
    },
    20000,
    'My Bookings did not load any booking rows.',
  );

  const searchInput = await waitForTestId(driver, 'booking-search');
  await searchInput.clear();
  await fillInputValue(driver, 'booking-search', searchText);

  await driver.wait(
    async () => {
      const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
      for (const row of rows) {
        if (!(await row.isDisplayed())) continue;
        const rowText = (await row.getText()).toLowerCase();
        if (rowText.includes(searchText.toLowerCase())) {
          return row;
        }
      }
      return null;
    },
    15000,
    `No booking list item matched search text "${searchText}".`,
  );

  await expandAllBookingsIfNeeded(driver);

  const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
  const matchingRows = [];

  for (const row of rows) {
    const rowText = (await row.getText()).toLowerCase();
    if (rowText.includes(searchText.toLowerCase())) {
      matchingRows.push(row);
    }
  }

  assert.ok(
    matchingRows.length > 0,
    `Expected at least one booking row containing "${searchText}" in My Bookings.`,
  );

  const statusAttr = await matchingRows[0].getAttribute('data-booking-status');
  const statusText = await matchingRows[0].findElement(By.css('[data-testid="booking-status"]')).getText();

  assert.ok(
    ACCEPTABLE_STATUSES.has(statusAttr) || ACCEPTABLE_STATUS_LABELS.has(statusText),
    `Unexpected booking status "${statusAttr || statusText}". Expected an early pipeline status.`,
  );
}

describe('Vendor booking', function () {
  let driver;
  let selectedEventName;
  let detailsMarker;

  before(async function () {
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Vendor/community user can submit a booking request for an available event', async function () {
    detailsMarker = uniqueTestMarker(env.bookingDetails);

    await loginAsVendor(driver);
    await driver.get(`${env.baseUrl}/vendor-booking`);
    await waitForUrlContains(driver, '/vendor-booking');
    await waitForTestIdHidden(driver, 'booking-events-loading', 20000);
    await waitForTestId(driver, 'booking-form');

    selectedEventName = await openBookableEvent(driver);
    await fillBookingForm(driver, detailsMarker);
    const toastsBeforeSubmit = await getToastTexts(driver);
    await submitBookingForm(driver);

    let outcome;
    try {
      outcome = await waitForSubmitOutcome(driver, toastsBeforeSubmit);
    } catch (error) {
      await driver.get(`${env.baseUrl}/dashboard`);
      await waitForTestId(driver, 'vendor-dashboard-root');
      await assertBookingVisible(driver, detailsMarker);
      return;
    }

    if (outcome.type === 'error') {
      throw new Error(`Booking submit failed: ${outcome.message}`);
    }

    if (outcome.type === 'duplicate') {
      await driver.get(`${env.baseUrl}/dashboard`);
    } else {
      try {
        await waitForUrlContains(driver, '/dashboard', 15000);
      } catch {
        await driver.get(`${env.baseUrl}/dashboard`);
      }
    }

    await waitForTestId(driver, 'vendor-dashboard-root');

    const searchTerms = [detailsMarker, env.bookingEventName, selectedEventName].filter(Boolean);
    let verified = false;
    let lastError;

    for (const term of searchTerms) {
      try {
        await assertBookingVisible(driver, term);
        verified = true;
        break;
      } catch (error) {
        lastError = error;
        const searchInput = await driver.findElement(By.css('[data-testid="booking-search"]'));
        await searchInput.clear();
      }
    }

    if (!verified) {
      throw lastError || new Error('Could not verify the booking in My Bookings.');
    }
  });
});
