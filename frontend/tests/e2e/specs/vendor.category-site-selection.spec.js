import { strict as assert } from 'node:assert';
import { afterEach, before, beforeEach, describe, it } from 'mocha';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsCommunityVendor } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import {
  cleanupPhase39Fixtures,
  createPhase39Fixtures,
  occupyPhase39Site,
  phase39FixtureStatus,
} from '../helpers/phase39-fixtures.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 3.9 category-first vendor site selection', function () {
  let driver;
  let fixtures;

  before(async function () {
    this.timeout(60000);
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(function () {
    this.timeout(60000);
    fixtures = createPhase39Fixtures();
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupPhase39Fixtures();
  });

  it('books contiguous Food sites through the row-grouped TGV flow', async function () {
    this.timeout(120000);
    await loginAndOpenBooking(driver, fixtures);

    await waitForTestId(driver, 'vendor-category-profile-suggestion');
    await selectCategory(driver, fixtures.food_category_id);
    await waitForAvailability(driver);

    assert.equal(
      (await driver.findElements(By.css(`[data-testid="event-site-row-${fixtures.row_a_id}"]`))).length,
      0,
      'The incompatible thrift row must not be rendered.',
    );
    const foodRow = await waitForTestId(driver, `event-site-row-${fixtures.row_b_id}`);
    assert.match(await foodRow.getText(), /Row B[\s\S]*Food & Beverages/);

    const marker = `E2E-P39 category-first ${Date.now()}`;
    await setInput(driver, 'booking-details', marker);
    await clickSite(driver, 'B01');
    await clickSite(driver, 'B02');

    const summary = await waitForTestId(driver, 'event-site-selection-summary');
    const summaryText = await summary.getText();
    assert.match(summaryText, /Food & Beverages/);
    assert.match(summaryText, /Row B/);
    assert.match(summaryText, /B01, B02/);
    assert.match(summaryText, /Bilangan Tapak\s*2/);
    assert.match(summaryText, /Hari Acara\s*2/);
    assert.match(summaryText, /Jumlah:\s*RM 60\.00/);

    await submitBooking(driver);
    await driver.wait(
      async () => (await driver.getCurrentUrl()).includes('/dashboard'),
      30000,
      'Successful booking should redirect to the dashboard.',
    );

    const status = phase39FixtureStatus();
    assert.equal(status.vendor_bookings, 1);
    assert.equal(status.vendor_invoices, 1);
    assert.equal(status.vendor_allocations, 4);
    assert.equal(status.latest_vendor_category_id, fixtures.food_category_id);
    assert.equal(status.latest_vendor_category_snapshot, 'Food & Beverages');
    assert.equal(status.latest_vendor_product_category, 'Food & Beverages');
  });

  it('clears sites but preserves product details when category changes', async function () {
    this.timeout(90000);
    await loginAndOpenBooking(driver, fixtures);

    await selectCategory(driver, fixtures.thrift_category_id);
    await waitForAvailability(driver);
    const marker = `E2E-P39 category-change ${Date.now()}`;
    await setInput(driver, 'booking-details', marker);
    await clickSite(driver, 'A01');
    await clickSite(driver, 'A02');
    await waitForTestId(driver, 'event-site-selection-summary');

    await selectCategory(driver, fixtures.food_category_id);
    await waitForAvailability(driver);
    await waitForTestIdHidden(driver, 'event-site-selection-summary');

    assert.equal(
      await (await waitForTestId(driver, 'booking-details')).getAttribute('value'),
      marker,
    );
    assert.equal(
      (await driver.findElements(By.css('[data-testid="event-site-tile-A01"]'))).length,
      0,
    );
    await waitForTestId(driver, 'event-site-tile-B01');

    const pageText = await (await waitForTestId(driver, 'booking-page-root')).getText();
    assert.match(pageText, /Kategori telah ditukar|Kategori jualan telah ditukar/);
  });

  it('refreshes a stale selection after a 409 without partial booking or auto-submit', async function () {
    this.timeout(120000);
    await loginAndOpenBooking(driver, fixtures);
    await selectCategory(driver, fixtures.food_category_id);
    await waitForAvailability(driver);

    const marker = `E2E-P39 conflict ${Date.now()}`;
    await setInput(driver, 'booking-details', marker);
    await clickSite(driver, 'B01');
    await clickSite(driver, 'B02');
    occupyPhase39Site('B02');

    await submitBooking(driver);
    await waitForTestId(driver, 'event-site-selection-error', 30000);
    const removed = await waitForTestId(driver, 'event-site-removed-stale', 30000);
    assert.match(await removed.getText(), /B02/);
    assert.ok((await driver.getCurrentUrl()).includes('/vendor-booking'));
    assert.equal(
      await (await waitForTestId(driver, 'booking-details')).getAttribute('value'),
      marker,
    );

    const status = phase39FixtureStatus();
    assert.equal(status.vendor_bookings, 0);
    assert.equal(status.vendor_invoices, 0);
    assert.equal(status.vendor_allocations, 0);
  });

  it('rejects a crafted incompatible category/site payload atomically', async function () {
    const token = await apiLogin(fixtures.vendor_email, fixtures.vendor_password);
    const response = await fetch(`${env.apiBaseUrl}/bookings`, {
      method: 'POST',
      headers: jsonHeaders(token),
      body: JSON.stringify({
        event_id: fixtures.event_id,
        vendor_category_id: fixtures.food_category_id,
        event_site_ids: [fixtures.site_ids.A01],
        product_details: 'E2E-P39 crafted incompatible payload',
      }),
    });
    assert.equal(response.status, 422);
    const body = await response.json();
    assert.equal(body.error, 'SITE_CATEGORY_INCOMPATIBLE');

    const status = phase39FixtureStatus();
    assert.equal(status.vendor_bookings, 0);
    assert.equal(status.vendor_invoices, 0);
    assert.equal(status.vendor_allocations, 0);
  });

  it('denies Organizer, CMart Management, and guest booking creation', async function () {
    const payload = {
      event_id: fixtures.event_id,
      vendor_category_id: fixtures.food_category_id,
      event_site_ids: [fixtures.site_ids.B01],
      product_details: 'E2E-P39 authorization probe',
    };

    for (const [email, password] of [
      [fixtures.organizer_email, fixtures.organizer_password],
      [fixtures.cmart_management_email, fixtures.cmart_management_password],
    ]) {
      const token = await apiLogin(email, password);
      const response = await fetch(`${env.apiBaseUrl}/bookings`, {
        method: 'POST',
        headers: jsonHeaders(token),
        body: JSON.stringify(payload),
      });
      assert.equal(response.status, 403);
    }

    const guest = await fetch(`${env.apiBaseUrl}/bookings`, {
      method: 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify(payload),
    });
    assert.equal(guest.status, 401);

    const status = phase39FixtureStatus();
    assert.equal(status.vendor_bookings, 0);
  });
});

async function loginAndOpenBooking(driver, fixture) {
  await loginAsCommunityVendor(driver, {
    email: fixture.vendor_email,
    password: fixture.vendor_password,
  });
  await driver.get(`${env.baseUrl}/vendor-booking?event_id=${fixture.event_id}`);
  await waitForTestIdHidden(driver, 'booking-events-loading', 30000);
  await waitForTestId(driver, 'booking-form');
}

async function selectCategory(driver, categoryId) {
  const option = await driver.findElement(
    By.css(`[data-testid="vendor-category-option"][data-category-id="${categoryId}"]`),
  );
  await driver.executeScript('arguments[0].click();', option);
}

async function waitForAvailability(driver) {
  await waitForTestId(driver, 'event-site-selector');
  await waitForTestIdHidden(driver, 'event-site-selector-loading', 30000);
}

async function setInput(driver, testId, value) {
  const input = await waitForTestId(driver, testId);
  await driver.executeScript(
    `const field = arguments[0];
     const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
     setter.call(field, arguments[1]);
     field.dispatchEvent(new Event('input', { bubbles: true }));
     field.dispatchEvent(new Event('change', { bubbles: true }));`,
    input,
    value,
  );
}

async function clickSite(driver, label) {
  const site = await waitForTestId(driver, `event-site-tile-${label}`);
  await driver.executeScript('arguments[0].click();', site);
}

async function submitBooking(driver) {
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="booking-form"]');
    if (!form) throw new Error('Booking form not found.');
    form.requestSubmit();
  `);
}

async function apiLogin(email, password) {
  const response = await fetch(`${env.apiBaseUrl}/auth/login`, {
    method: 'POST',
    headers: jsonHeaders(),
    body: JSON.stringify({ email, password }),
  });
  assert.equal(response.status, 200);
  const body = await response.json();
  assert.ok(body.token);
  return body.token;
}

function jsonHeaders(token = null) {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}
