import { strict as assert } from 'node:assert';
import { after, afterEach, before, beforeEach, describe, it } from 'mocha';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import {
  loginAsCommunityVendor,
  loginAsOrganizer,
  logout,
} from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { selectBookingCategory } from '../helpers/vendor-categories.js';
import {
  cleanupVisualParkingFixtures,
  createVisualParkingFixtures,
} from '../helpers/visual-parking-fixtures.js';
import { waitForTestId, waitForTestIdHidden } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Visual parking layout builder journey', function () {
  let driver;
  let fixtures;
  const originalEnv = {
    organizerEmail: env.organizerEmail,
    organizerPassword: env.organizerPassword,
    vendorEmail: env.vendorEmail,
    vendorPassword: env.vendorPassword,
  };

  before(async function () {
    this.timeout(60000);
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(function () {
    this.timeout(60000);
    fixtures = createVisualParkingFixtures();
    env.organizerEmail = fixtures.organizer_email;
    env.organizerPassword = fixtures.organizer_password;
    env.vendorEmail = fixtures.vendor_email;
    env.vendorPassword = fixtures.vendor_password;
    process.env.E2E_ORGANIZER_EMAIL = fixtures.organizer_email;
    process.env.E2E_ORGANIZER_PASSWORD = fixtures.organizer_password;
    process.env.E2E_VENDOR_EMAIL = fixtures.vendor_email;
    process.env.E2E_VENDOR_PASSWORD = fixtures.vendor_password;
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupVisualParkingFixtures();
    env.organizerEmail = originalEnv.organizerEmail;
    env.organizerPassword = originalEnv.organizerPassword;
    env.vendorEmail = originalEnv.vendorEmail;
    env.vendorPassword = originalEnv.vendorPassword;
    process.env.E2E_ORGANIZER_EMAIL = originalEnv.organizerEmail;
    process.env.E2E_ORGANIZER_PASSWORD = originalEnv.organizerPassword;
    process.env.E2E_VENDOR_EMAIL = originalEnv.vendorEmail;
    process.env.E2E_VENDOR_PASSWORD = originalEnv.vendorPassword;
  });

  after(async function () {
    this.timeout(15000);
    if (driver) {
      await driver.quit();
    }
  });

  it('generates 4x16 map, blocks unavailable site, books contiguous sites, and preserves language scopes', async function () {
    this.timeout(180000);

    await loginAsOrganizer(driver);
    await openOrganizerLayout(driver, fixtures.event_id);

    const panel = await waitForTestId(driver, 'organizer-event-layout-panel');
    await driver.wait(async () => panel.isDisplayed(), 15000, 'Layout panel should be visible.');
    const panelText = await panel.getText();
    assert.match(panelText, /Generate Standard Parking Layout|Parking Layout|Site Layout/);
    assert.doesNotMatch(panelText, /Pilih Tapak|Laluan Kenderaan|Tersedia|Ditempah/);

    await waitForTestId(driver, 'layout-empty-state');
    await driver.findElement(By.css('[data-testid="layout-empty-generate-standard"]')).click();
    await waitForTestId(driver, 'standard-parking-layout-modal');

    await selectByTestId(driver, 'standard-layout-space-select', String(fixtures.space_id));
    await selectByTestId(driver, 'standard-layout-category-A', String(fixtures.food_category_id));
    await selectByTestId(driver, 'standard-layout-category-B', String(fixtures.food_category_id));
    await selectByTestId(driver, 'standard-layout-category-C', String(fixtures.thrift_category_id));
    await selectByTestId(driver, 'standard-layout-category-D', String(fixtures.thrift_category_id));

    await driver.findElement(By.css('[data-testid="standard-layout-submit"]')).click();
    await waitForTestIdHidden(driver, 'standard-parking-layout-modal', 30000);
    await waitForTestId(driver, 'layout-visual-workspace', 30000);
    await waitForTestId(driver, 'visual-parking-layout');
    await waitForTestId(driver, 'visual-parking-exit');
    await waitForTestId(driver, 'visual-parking-aisle');
    await waitForTestId(driver, 'visual-parking-entrance');
    await waitForTestId(driver, 'visual-parking-tile-A01');
    await waitForTestId(driver, 'visual-parking-tile-D16');

    const aisleText = await (await waitForTestId(driver, 'visual-parking-aisle')).getText();
    assert.match(aisleText, /Vehicle Aisle/i);
    assert.doesNotMatch(aisleText, /Laluan Kenderaan/);

    const tiles = await driver.findElements(By.css('[data-testid^="visual-parking-tile-"]'));
    assert.equal(tiles.length, 64, 'Organizer map must render all 64 parking sites.');

    await driver.findElement(By.css('[data-testid="visual-parking-tile-A01"]')).click();
    await waitForTestId(driver, 'organizer-focused-site-controls');
    await driver.findElement(By.css('[data-testid="focused-site-set-unavailable"]')).click();
    await driver.wait(async () => {
      const tile = await driver.findElement(By.css('[data-testid="visual-parking-tile-A01"]'));
      return (await tile.getAttribute('data-status')) === 'unavailable';
    }, 15000, 'A01 should become unavailable on the organizer map.');

    await logout(driver, { management: true });

    await loginAsCommunityVendor(driver, {
      email: fixtures.vendor_email,
      password: fixtures.vendor_password,
    });
    await openBookingForm(driver, fixtures.event_id);
    await selectBookingCategory(driver, 'Food & Beverages');

    await waitForTestId(driver, 'event-site-selector');
    await waitForTestId(driver, 'visual-parking-layout');
    const vendorMap = await waitForTestId(driver, 'event-site-map');
    const vendorText = await vendorMap.getText();
    assert.match(vendorText, /Keluar|Masuk|Tersedia|Dipilih|TIDAK TERSEDIA/i);
    assert.doesNotMatch(vendorText, /Vehicle Aisle|Generate Standard Parking Layout/);

    const unavailableTile = await waitForTestId(driver, 'event-site-tile-A01');
    assert.equal(await unavailableTile.getAttribute('data-status'), 'unavailable');
    assert.equal(await unavailableTile.getAttribute('aria-disabled'), 'true');

    await setInputValue(driver, 'booking-business-name', env.bookingBusinessName || 'VPL E2E Vendor');
    await setInputValue(driver, 'booking-details', `E2E-VPL contiguous booking ${Date.now()}`);

    await clickVendorTile(driver, 'A02');
    await clickVendorTile(driver, 'A03');
    await waitForTestId(driver, 'event-site-selection-summary');
    const preview = await (await waitForTestId(driver, 'event-site-preview-amount')).getText();
    assert.match(preview, /RM\s*60\.00/);

    await submitBookingForm(driver);
    await driver.wait(
      async () => (await driver.getCurrentUrl()).includes('/dashboard'),
      30000,
      'Vendor booking should redirect to dashboard after success.',
    );

    await logout(driver);
    await loginAsOrganizer(driver);
    await openOrganizerLayout(driver, fixtures.event_id);
    await waitForTestId(driver, 'visual-parking-tile-A02');

    await driver.wait(async () => {
      const a02 = await driver.findElement(By.css('[data-testid="visual-parking-tile-A02"]'));
      const a03 = await driver.findElement(By.css('[data-testid="visual-parking-tile-A03"]'));
      const statusA02 = await a02.getAttribute('data-status');
      const statusA03 = await a03.getAttribute('data-status');
      return ['reserved', 'confirmed'].includes(statusA02)
        && ['reserved', 'confirmed'].includes(statusA03);
    }, 20000, 'Booked sites must show reserved/confirmed visual state for the organizer.');

    const a01 = await driver.findElement(By.css('[data-testid="visual-parking-tile-A01"]'));
    assert.equal(await a01.getAttribute('data-status'), 'unavailable');
  });
});

async function openOrganizerLayout(driver, eventId) {
  await driver.get(`${env.baseUrl}/admin?eventId=${encodeURIComponent(eventId)}#layout`);
  await waitForTestId(driver, 'management-dashboard-root', 30000);
  await driver.wait(async () => {
    const url = await driver.getCurrentUrl();
    return url.includes('#layout');
  }, 10000, 'Admin URL should retain the layout hash.');
  const panel = await waitForTestId(driver, 'organizer-event-layout-panel', 30000);
  await driver.wait(async () => panel.isDisplayed(), 15000, 'Organizer layout panel must be displayed.');
  await waitForTestIdHidden(driver, 'layout-loading-state', 30000);
}

async function openBookingForm(driver, eventId) {
  await driver.get(`${env.baseUrl}/vendor-booking?event_id=${eventId}`);
  await waitForTestIdHidden(driver, 'booking-events-loading', 20000);
  await waitForTestId(driver, 'booking-form');
  await waitForTestId(driver, 'event-site-selector', 20000);
  await waitForTestIdHidden(driver, 'event-site-selector-loading', 20000);
}

async function selectByTestId(driver, testId, value) {
  const select = await waitForTestId(driver, testId);
  await driver.executeScript(
    `const el = arguments[0];
     el.value = arguments[1];
     el.dispatchEvent(new Event('input', { bubbles: true }));
     el.dispatchEvent(new Event('change', { bubbles: true }));`,
    select,
    value,
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

async function clickVendorTile(driver, label) {
  const tile = await waitForTestId(driver, `event-site-tile-${label}`);
  await driver.executeScript('arguments[0].click();', tile);
}

async function submitBookingForm(driver) {
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="booking-form"]');
    if (!form) throw new Error('Booking form not found.');
    form.requestSubmit();
  `);
}
