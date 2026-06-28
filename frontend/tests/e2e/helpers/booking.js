import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsCommunityVendor, loginAsVendor } from './auth.js';
import { uniqueTestMarker } from './actions.js';
import { waitForTestId, waitForTestIdHidden, waitForUrlContains } from './wait.js';

const NO_EVENTS_MESSAGE =
  'No available event found for E2E booking test. Please create an upcoming active event from staff dashboard first.';

const PRODUCT_CATEGORIES = [
  'Pre-loved / Thrift',
  'Food & Beverages',
  'Clothing & Apparel',
  'Handicrafts & Art',
  'Electronics & Gadgets',
  'Others',
];

export function resolveCategory(category) {
  const needle = String(category || '').toLowerCase().trim();
  if (!needle) return 'Food & Beverages';

  const exact = PRODUCT_CATEGORIES.find((option) => option.toLowerCase() === needle);
  if (exact) return exact;

  const partial = PRODUCT_CATEGORIES.find(
    (option) => option.toLowerCase().includes(needle) || needle.includes(option.toLowerCase()),
  );
  return partial || category;
}

export async function fillInputValue(driver, testId, value) {
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

export async function setSelectValue(driver, testId, value) {
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

async function getToastTexts(driver) {
  const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
  const texts = [];

  for (const toast of toasts) {
    texts.push((await toast.getText()).trim());
  }

  return texts.filter(Boolean);
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

  return chosen.text.trim();
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

async function vendorBookingExists(driver, marker) {
  await driver.get(`${env.baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root', 30000);
  await fillInputValue(driver, 'booking-search', marker);

  const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
  for (const row of rows) {
    const text = (await row.getText()).toLowerCase();
    if (text.includes(marker.toLowerCase())) {
      return true;
    }
  }

  return false;
}

async function findReusablePendingStaffMarker(driver) {
  await driver.get(`${env.baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root');
  await fillInputValue(driver, 'booking-search', env.bookingDetails);

  const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
  for (const row of rows) {
    const text = await row.getText();
    const status = await row.getAttribute('data-booking-status');
    if (!text.toLowerCase().includes(env.bookingDetails.toLowerCase())) continue;
    if (status !== 'Pending_Staff') continue;

    const parts = text.split('·');
    if (parts.length >= 2) {
      return parts[parts.length - 1].trim();
    }

    return env.bookingDetails;
  }

  return null;
}

async function prepareVendorBookingPage(driver) {
  await driver.get(`${env.baseUrl}/vendor-booking`);
  await waitForUrlContains(driver, '/vendor-booking');
  await waitForTestIdHidden(driver, 'booking-events-loading', 20000);
  await waitForTestId(driver, 'booking-form');
}

/**
 * Creates a vendor booking with a unique E2E marker, or reuses an existing matching booking.
 */
export async function ensureE2EBookingExists(
  driver,
  marker = uniqueTestMarker(env.bookingDetails),
  { allowReuse = true, vendorCredentials } = {},
) {
  if (vendorCredentials) {
    await loginAsCommunityVendor(driver, vendorCredentials, { roleLabel: 'Vendor' });
  } else {
    await loginAsVendor(driver);
  }
  await prepareVendorBookingPage(driver);

  if (await vendorBookingExists(driver, marker)) {
    return { marker };
  }

  if (allowReuse && !vendorCredentials) {
    const reusableMarker = await findReusablePendingStaffMarker(driver);
    if (reusableMarker) {
      return { marker: reusableMarker };
    }
  }

  await prepareVendorBookingPage(driver);
  await openBookableEvent(driver);
  await fillBookingForm(driver, marker);
  const toastsBeforeSubmit = await getToastTexts(driver);
  await submitBookingForm(driver);

  let outcome;
  try {
    outcome = await waitForSubmitOutcome(driver, toastsBeforeSubmit);
  } catch {
    await driver.get(`${env.baseUrl}/dashboard`);
    if (await vendorBookingExists(driver, marker)) {
      return { marker };
    }
    const fallbackMarker = await findReusablePendingStaffMarker(driver);
    if (fallbackMarker) {
      return { marker: fallbackMarker };
    }
    throw new Error(`Failed to create or locate an E2E-marked booking for "${marker}".`);
  }

  if (outcome.type === 'error') {
    throw new Error(`E2E booking creation failed: ${outcome.message}`);
  }

  return { marker };
}
