import { strict as assert } from 'node:assert';
import { requireStaffCredentials } from '../config/env.js';
import { loginAsStaff } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Staff authentication', function () {
  this.timeout(120000);

  let driver;

  before(async function () {
    requireStaffCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Staff user can log in and reach the admin workspace', async function () {
    await loginAsStaff(driver);
    const dashboardRoot = await waitForTestId(driver, 'staff-dashboard-root');
    assert.equal(await dashboardRoot.isDisplayed(), true);
  });
});
