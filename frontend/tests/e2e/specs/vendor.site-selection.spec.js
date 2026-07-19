import { strict as assert } from 'node:assert';
import { afterEach, before, beforeEach, describe, it } from 'mocha';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsCommunityVendor } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { createSiteFixtures, cleanupSiteFixtures } from '../helpers/site-fixtures.js';
import { selectBookingCategory } from '../helpers/vendor-categories.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

/**
 * Phase 2A.8.1 — cinema-style site selection browser flow.
 *
 * Uses temporary test-scoped EventSites/EventDays created by the backend
 * `e2e:site-fixtures` command. Does NOT depend on permanent local layout data.
 * Fixtures are recreated per test so each scenario starts from a clean layout.
 */
describe('Vendor cinema-style site selection', function () {
  let driver;
  let fixtures;

  before(async function () {
    this.timeout(60000);
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(function () {
    this.timeout(60000);
    fixtures = createSiteFixtures();
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupSiteFixtures();
  });

  it('loads real availability, selects adjacent sites, and submits event_site_ids', async function () {
    this.timeout(90000);

    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });

    await openBookingForm(driver, fixtures.event_id);

    await setInputValue(driver, 'booking-business-name', env.bookingBusinessName);
    await selectBookingCategory(driver, 'Food & Beverages');
    await setInputValue(driver, 'booking-details', `E2E-SITE-FIX success ${Date.now()}`);

    const daysText = await (await waitForTestId(driver, 'event-site-days-summary')).getText();
    assert.ok(daysText.length > 0, 'Active EventDay summary should be displayed.');

    const [labelA, labelB] = fixtures.site_labels;
    await waitForTestId(driver, `event-site-tile-${labelA}`);
    await waitForTestId(driver, `event-site-tile-${labelB}`);

    await clickTile(driver, labelA);
    await clickTile(driver, labelB);

    await waitForTestId(driver, 'event-site-selection-summary');
    const previewText = await (await waitForTestId(driver, 'event-site-preview-amount')).getText();
    assert.match(previewText, /RM\s*60\.00/, `Preview total should be RM 60.00, got: ${previewText}`);

    await driver.wait(
      async () => (await driver.findElement(By.css('[data-testid="booking-submit"]'))).isEnabled(),
      10000,
      'Submit should be enabled once a valid contiguous selection exists.',
    );

    await submitBookingForm(driver);

    await driver.wait(
      async () => (await driver.getCurrentUrl()).includes('/dashboard'),
      25000,
      'Booking submission should redirect to the vendor dashboard on success.',
    );
    await waitForTestId(driver, 'vendor-dashboard-root', 20000);
  });

  it('refreshes availability and prunes the selection when submission hits a 409 conflict', async function () {
    this.timeout(90000);

    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });

    await openBookingForm(driver, fixtures.event_id);

    const [labelA] = fixtures.site_labels;
    const conflictSiteId = fixtures.site_ids[0];

    await setInputValue(driver, 'booking-business-name', env.bookingBusinessName);
    await selectBookingCategory(driver, 'Food & Beverages');
    const detailsMarker = `E2E-SITE-FIX conflict ${Date.now()}`;
    await setInputValue(driver, 'booking-details', detailsMarker);

    await waitForTestId(driver, `event-site-tile-${labelA}`);
    await clickTile(driver, labelA);
    await waitForTestId(driver, 'event-site-selection-summary');

    // A competing reservation occupies that exact site before submission.
    await reserveSiteViaApi(fixtures, conflictSiteId);

    await submitBookingForm(driver);

    // 409 handler surfaces a conflict message near the selector...
    await waitForTestId(driver, 'event-site-selection-error', 20000);

    // ...availability refreshes and the conflicted site becomes non-selectable.
    await driver.wait(
      async () => {
        const tile = await driver.findElement(By.css(`[data-testid="event-site-tile-${labelA}"]`));
        const disabled = await tile.getAttribute('disabled');
        const pressed = await tile.getAttribute('aria-pressed');
        return Boolean(disabled) && pressed === 'false';
      },
      20000,
      'Conflicted site should become disabled and unselected after 409 refresh.',
    );

    // The invalid selection is pruned (summary removed) and no auto-resubmit occurred.
    await waitForTestIdHidden(driver, 'event-site-selection-summary', 10000);
    assert.ok(
      (await driver.getCurrentUrl()).includes('/vendor-booking'),
      'Form must remain on the booking page (no automatic resubmission).',
    );

    // Unrelated form fields are preserved.
    const detailsValue = await (await waitForTestId(driver, 'booking-details')).getAttribute('value');
    assert.equal(detailsValue, detailsMarker, 'Product details must survive conflict handling.');
  });
});

async function openBookingForm(driver, eventId) {
  await driver.get(`${env.baseUrl}/vendor-booking?event_id=${eventId}`);
  await waitForTestIdHidden(driver, 'booking-events-loading', 20000);
  await waitForTestId(driver, 'booking-form');
  await waitForTestId(driver, 'event-site-selector', 20000);
  await waitForTestIdHidden(driver, 'event-site-selector-loading', 20000);
}

async function submitBookingForm(driver) {
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="booking-form"]');
    if (!form) throw new Error('Booking form not found.');
    form.requestSubmit();
  `);
}

async function reserveSiteViaApi(fixtures, siteId) {
  const loginResponse = await fetch(`${env.apiBaseUrl}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: fixtures.vendor_email, password: fixtures.vendor_password }),
  });
  const loginBody = await loginResponse.json();
  const token = loginBody.token || loginBody.access_token;
  assert.ok(token, 'Competing reservation requires an auth token.');

  const bookingResponse = await fetch(`${env.apiBaseUrl}/bookings`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({
      event_id: fixtures.event_id,
      event_site_ids: [siteId],
      product_category: 'Food & Beverages',
      product_details: 'E2E-SITE-FIX competing reservation',
    }),
  });

  assert.equal(
    bookingResponse.status,
    201,
    `Competing reservation should be created (201), got ${bookingResponse.status}.`,
  );
}

async function setInputValue(driver, testId, value) {
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

async function clickTile(driver, label) {
  const tile = await waitForTestId(driver, `event-site-tile-${label}`);
  await driver.executeScript('arguments[0].click();', tile);
}
