import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env, requireStaffCredentials } from '../config/env.js';
import { loginAsStaff, logout } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Staff tools operational snapshot', function () {
  this.timeout(120000);

  let driver;

  before(async function () {
    requireStaffCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Staff tools page shows Operational Snapshot instead of Live Analytics', async function () {
    await loginAsStaff(driver);
    await driver.get(`${env.baseUrl}/admin#tools`);

    await waitForTestId(driver, 'staff-dashboard-root');
    await waitForTestId(driver, 'staff-operational-snapshot');

    const pageSource = await driver.getPageSource();
    assert.ok(
      pageSource.includes('Operational Snapshot'),
      'Staff tools must show Operational Snapshot heading.',
    );
    assert.ok(
      !pageSource.includes('Our Impact'),
      'Staff tools must not show Our Impact analytics section.',
    );
    assert.ok(
      !pageSource.includes('Economic Value Generated'),
      'Staff tools must not show economic value metrics.',
    );
    assert.ok(
      !pageSource.includes('Live Analytics'),
      'Staff tools must not show Live Analytics label.',
    );

    const snapshot = await driver.findElement(By.css('[data-testid="staff-operational-snapshot"]'));
    const snapshotText = await snapshot.getText();
    assert.ok(
      snapshotText.includes('Pending Staff Review'),
      'Operational snapshot must include pending staff review card.',
    );

    const pendingCard = await driver.findElement(
      By.css('[data-testid="staff-operational-card-pending_staff_review"]'),
    );
    await pendingCard.click();

    await waitForTestId(driver, 'staff-bookings-root');

    const currentUrl = await driver.getCurrentUrl();
    assert.ok(
      currentUrl.includes('#bookings'),
      'Clicking an operational snapshot card body must navigate to the Bookings section.',
    );

    await logout(driver);
  });
});
