/* global describe, before, beforeEach, afterEach, it */
import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env, requireOrganizerCredentials } from '../config/env.js';
import { loginAsOrganizer, logout } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { openOrganizerBookings } from '../helpers/organizer-bookings.js';
import {
  cleanupSiteFixtures,
  createReleasedDayRecoveryFixture,
  recoveryAddCompetingAllocation,
  recoveryFixtureStatus,
} from '../helpers/site-fixtures.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

const MARKER = 'E2E-SITE-FIX';

describe('Organizer released-day recovery', function () {
  let driver;
  let fixtures;

  before(async function () {
    this.timeout(90000);
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(function () {
    this.timeout(120000);
    fixtures = createReleasedDayRecoveryFixture();
  });

  afterEach(function () {
    this.timeout(30000);
    cleanupSiteFixtures();
  });

  it('shows recoverable, partially blocked, and fully blocked recovery slices from real backend state', async function () {
    this.timeout(300000);

    await loginAsOrganizer(driver);
    await openOrganizerBookings(driver, env.baseUrl);
    await waitForTestId(driver, 'organizer-tab-released-recovery');
    await driver.executeScript("document.querySelector('[data-testid=\"organizer-tab-released-recovery\"]')?.click();");
    await waitForTestId(driver, 'organizer-released-day-recovery-panel');
    await driver.wait(async () => {
      const rows = await driver.findElements(By.css('[data-testid="recovery-queue-row"]'));
      const empty = await driver.findElements(By.css('[data-testid="recovery-empty-state"]'));
      const loading = await driver.findElements(By.css('[data-testid="recovery-loading"]'));
      return (rows.length > 0 || empty.length > 0) && loading.length === 0;
    }, 30000, 'Recovery queue did not finish loading.');

    const panel = await waitForTestId(driver, 'organizer-released-day-recovery-panel');
    const initialText = await panel.getText();
    assert.match(initialText, new RegExp(MARKER, 'i'));
    assert.ok(fixtures.booking_id, 'Recovery fixture must provide a booking id.');
    assert.ok(fixtures.released_day_id, 'Recovery fixture must provide a released EventDay id.');
    assert.match(initialText, /BKG-/);
    assert.match(initialText, /Recoverable/i);
    assert.match(initialText, /Paid/i);
    assert.match(initialText, /A01/);
    assert.match(initialText, /A02/);
    assert.match(initialText, /Unavailable/i);

    await driver.executeScript(
      'document.querySelector(\'[data-testid="recovery-view-detail"]\')?.click();',
    );
    const detail = await waitForTestId(driver, 'recovery-detail-modal');
    const detailText = await detail.getText();
    assert.match(detailText, /Released-Day Recovery Detail/i);
    assert.match(detailText, /Emergency family commitment/i);
    assert.equal((await detail.findElements(By.css('[data-testid="recovery-assign-vendor"]'))).length, 0);
    assert.equal((await detail.findElements(By.css('button'))).length > 0, true);
    await driver.executeScript(
      'document.querySelector(\'[data-testid="recovery-detail-modal"] button[aria-label="Close recovery detail"]\')?.click();',
    );

    recoveryAddCompetingAllocation('A02');
    await driver.navigate().refresh();
    await waitForTestId(driver, 'organizer-bookings-root');
    await waitForTestId(driver, 'organizer-tab-released-recovery', 30000);
    await driver.executeScript("document.querySelector('[data-testid=\"organizer-tab-released-recovery\"]')?.click();");
    await waitForTestId(driver, 'organizer-released-day-recovery-panel', 30000);
    await driver.wait(async () => {
      const rows = await driver.findElements(By.css('[data-testid="recovery-queue-row"]'));
      return rows.length > 0;
    }, 30000, 'Recovery queue did not reload after partial blocker.');
    const partialPanel = await waitForTestId(driver, 'organizer-released-day-recovery-panel');
    const partialText = await partialPanel.getText();
    assert.match(partialText, /Partially Blocked/i);
    assert.match(partialText, /Occupied by another active booking/i);

    recoveryAddCompetingAllocation('A01');
    await driver.navigate().refresh();
    await waitForTestId(driver, 'organizer-bookings-root');
    await waitForTestId(driver, 'organizer-tab-released-recovery', 30000);
    await driver.executeScript("document.querySelector('[data-testid=\"organizer-tab-released-recovery\"]')?.click();");
    await waitForTestId(driver, 'organizer-released-day-recovery-panel', 30000);
    await driver.wait(async () => {
      const rows = await driver.findElements(By.css('[data-testid="recovery-queue-row"]'));
      return rows.length > 0;
    }, 30000, 'Recovery queue did not reload after full blocker.');
    const blockedPanel = await waitForTestId(driver, 'organizer-released-day-recovery-panel');
    const blockedText = await blockedPanel.getText();
    assert.match(blockedText, /Fully Blocked/i);

    const status = recoveryFixtureStatus();
    assert.equal(status.approval_status, 'Approved');
    assert.equal(status.payment_status, 'Paid');
    assert.equal(status.allocation_count, 6);
    assert.equal(status.released_count, 2);
    assert.equal(status.active_count, 4);
    assert.equal(status.exception_count, 1);
    assert.ok(status.audit_count >= 1);

    await logout(driver);
  });
});
