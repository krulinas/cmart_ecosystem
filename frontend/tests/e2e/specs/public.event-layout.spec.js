import { strict as assert } from 'node:assert';
import process from 'node:process';
import { after, before, beforeEach, describe, it } from 'mocha';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { phase310FixtureStatus } from '../helpers/phase310-fixtures.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 3.10 public category layout', function () {
  let driver;

  const fixture = {
    publishedEventId: process.env.E2E_PUBLIC_LAYOUT_EVENT_ID,
    publishedEventTitle: process.env.E2E_PUBLIC_LAYOUT_EVENT_TITLE,
    unpublishedEventId: process.env.E2E_UNPUBLISHED_LAYOUT_EVENT_ID,
    unpublishedEventTitle: process.env.E2E_UNPUBLISHED_LAYOUT_EVENT_TITLE,
    endedEventId: process.env.E2E_ENDED_LAYOUT_EVENT_ID,
    closedEventId: process.env.E2E_CLOSED_LAYOUT_EVENT_ID,
    foodCategoryId: process.env.E2E_PUBLIC_LAYOUT_FOOD_CATEGORY_ID,
    privateRow: process.env.E2E_PUBLIC_LAYOUT_PRIVATE_ROW,
    unresolvedSite: process.env.E2E_PUBLIC_LAYOUT_UNRESOLVED_SITE,
    privateVendorName: process.env.E2E_PUBLIC_LAYOUT_PRIVATE_VENDOR_NAME,
    privateVendorEmail: process.env.E2E_PUBLIC_LAYOUT_PRIVATE_VENDOR_EMAIL,
    privateOverride: process.env.E2E_PUBLIC_LAYOUT_PRIVATE_OVERRIDE,
  };

  before(async function () {
    this.timeout(60000);
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(async function () {
    this.timeout(60000);
    await driver.manage().window().setRect({ width: 1280, height: 900 });
    await driver.get(env.baseUrl);
    await driver.executeScript('window.localStorage.clear(); window.sessionStorage.clear();');
    await driver.navigate().refresh();
    await waitForTestId(driver, 'public-events-root', 30000);
  });

  after(async function () {
    await quitDriver(driver);
  });

  it('guest views the published category map with real rows and site labels', async function () {
    await openEvent(driver, fixture.publishedEventTitle);
    const section = await waitForTestId(driver, 'public-event-layout-section', 30000);
    await waitForTestId(driver, 'public-layout-map', 30000);
    const text = await section.getText();

    assert.match(text, /Event Layout Map/);
    assert.match(text, /Row A/);
    assert.match(text, /Row B/);
    assert.match(text, /Pre-loved \/ Thrift/);
    assert.match(text, /Food & Beverages/);
    assert.match(text, /A01/);
    assert.match(text, /B02/);
    assert.equal(await driver.executeScript('return window.localStorage.getItem("token")'), null);
  });

  it('filters Food rows and restores all categories without a reload', async function () {
    await openEvent(driver, fixture.publishedEventTitle);
    await waitForTestId(driver, 'public-layout-map', 30000);
    const beforeUrl = await driver.getCurrentUrl();

    await clickCategory(driver, fixture.foodCategoryId);
    let rows = await visibleRowTexts(driver);
    assert.equal(rows.length, 1);
    assert.match(rows[0], /Row B[\s\S]*Food & Beverages/);
    assert.doesNotMatch(rows[0], /Row A/);

    await clickCategory(driver, 'all');
    rows = await visibleRowTexts(driver);
    assert.equal(rows.length, 2);
    assert.equal(await driver.getCurrentUrl(), beforeUrl);
  });

  it('excludes private rows, unresolved sites, occupancy, vendor identity and override details', async function () {
    await openEvent(driver, fixture.publishedEventTitle);
    const section = await waitForTestId(driver, 'public-event-layout-section', 30000);
    await waitForTestId(driver, 'public-layout-map', 30000);
    const text = await section.getText();
    const response = await fetch(`${env.apiBaseUrl}/events/${fixture.publishedEventId}/layout`);
    const json = JSON.stringify(await response.json()).toLowerCase();

    assert.equal(response.status, 200);
    assert.doesNotMatch(text, new RegExp(escapeRegex(fixture.privateRow), 'i'));
    assert.doesNotMatch(text, new RegExp(escapeRegex(fixture.unresolvedSite), 'i'));
    assert.doesNotMatch(json, new RegExp(escapeRegex(fixture.privateVendorName), 'i'));
    assert.doesNotMatch(json, new RegExp(escapeRegex(fixture.privateVendorEmail), 'i'));
    assert.doesNotMatch(json, new RegExp(escapeRegex(fixture.privateOverride), 'i'));
    assert.doesNotMatch(json, /booking|allocation|occupancy|reserved|confirmed|override|invoice|payment|active_lock/);
    assert.match(json, /"label":"b01"/);

    const status = phase310FixtureStatus();
    assert.equal(status.bookings, 1);
    assert.equal(status.allocations, 2);
    assert.equal(status.overrides, 1);
  });

  it('shows a safe unpublished state while the public event remains accessible', async function () {
    await openEvent(driver, fixture.unpublishedEventTitle);
    const unavailable = await waitForTestId(driver, 'public-layout-unavailable', 30000);

    assert.match(await unavailable.getText(), /The event layout has not been published yet/);
    assert.equal((await driver.findElements(By.css('[data-testid="public-layout-map"]'))).length, 0);
    const modal = await waitForTestId(driver, 'public-detail-modal');
    assert.match(await modal.getText(), new RegExp(escapeRegex(fixture.unpublishedEventTitle)));
    assert.doesNotMatch(await modal.getText(), /NO_PUBLIC_ROWS|ACTIVE_SITE_MISSING_ROW|blocking_reasons/);
  });

  it('keeps category controls and site markers usable at a mobile viewport without overflow', async function () {
    await driver.manage().window().setRect({ width: 390, height: 844 });
    await openEvent(driver, fixture.publishedEventTitle);
    await waitForTestId(driver, 'public-layout-map', 30000);

    const filter = await waitForTestId(driver, 'public-layout-category-filter');
    const sites = await driver.findElements(By.css('[data-testid="public-layout-site"]'));
    assert.equal(await filter.isDisplayed(), true);
    assert.ok(sites.length >= 4);
    assert.ok((await sites[0].getRect()).width >= 80);
    assert.equal(
      await driver.executeScript('return document.documentElement.scrollWidth <= window.innerWidth + 1;'),
      true,
    );
    const innerWidth = await driver.executeScript('return window.innerWidth;');
    assert.equal((await filter.getRect()).width <= innerWidth, true);
  });

  it('serves ended and closed published layouts as historical and keeps unpublished layouts unavailable', async function () {
    const ended = await fetch(`${env.apiBaseUrl}/events/${fixture.endedEventId}/layout`);
    const endedBody = await ended.json();
    const closed = await fetch(`${env.apiBaseUrl}/events/${fixture.closedEventId}/layout`);
    const closedBody = await closed.json();
    const unpublished = await fetch(`${env.apiBaseUrl}/events/${fixture.unpublishedEventId}/layout`);
    const unpublishedBody = await unpublished.json();

    assert.equal(ended.status, 200);
    assert.equal(endedBody.historical, true);
    assert.equal(endedBody.rows[0].label, 'Row E');
    assert.equal(closed.status, 200);
    assert.equal(closedBody.historical, true);
    assert.equal(closedBody.rows[0].label, 'Row C');
    assert.equal(unpublished.status, 404);
    assert.equal(unpublishedBody.error, 'PUBLIC_LAYOUT_NOT_AVAILABLE');
  });
});

async function openEvent(driver, title) {
  const card = await driver.wait(async () => {
    const cards = await driver.findElements(By.css('[data-testid="public-event-card"]'));
    for (const candidate of cards) {
      if ((await candidate.getText()).includes(title)) {
        return candidate;
      }
    }
    return false;
  }, 30000, `Public event card "${title}" was not found.`);

  await driver.executeScript('arguments[0].click();', card);
  await waitForTestId(driver, 'public-detail-modal', 15000);
}

async function clickCategory(driver, categoryId) {
  const button = await driver.findElement(
    By.css(`[data-testid="public-layout-category-option"][data-category-id="${categoryId}"]`),
  );
  await driver.executeScript('arguments[0].click();', button);
}

async function visibleRowTexts(driver) {
  const rows = await driver.findElements(By.css('[data-testid="public-layout-row"]'));
  const visible = [];
  for (const row of rows) {
    if (await row.isDisplayed()) visible.push(await row.getText());
  }
  return visible;
}

function escapeRegex(value = '') {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
