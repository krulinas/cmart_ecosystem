import { strict as assert } from 'node:assert';
import { requireManagerCredentials } from '../config/env.js';
import { loginAsManager } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Manager authentication', function () {
  this.timeout(120000);

  let driver;

  before(async function () {
    requireManagerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Manager user can log in and reach the admin workspace', async function () {
    await loginAsManager(driver);
    const dashboardRoot = await waitForTestId(driver, 'staff-dashboard-root');
    assert.equal(await dashboardRoot.isDisplayed(), true);
  });
});
