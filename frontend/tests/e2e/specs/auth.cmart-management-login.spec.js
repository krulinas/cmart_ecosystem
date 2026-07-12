import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { requireCmartManagementCredentials } from '../config/env.js';
import { loginAsCmartManagement } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('CMart management authentication', function () {
  this.timeout(120000);

  let driver;

  before(async function () {
    requireCmartManagementCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('cmart_management user sees news/reports nav and not bookings', async function () {
    await loginAsCmartManagement(driver);
    const dashboardRoot = await waitForTestId(driver, 'management-dashboard-root');
    assert.equal(await dashboardRoot.isDisplayed(), true);

    const bodyText = await driver.findElement(By.css('body')).getText();
    assert.ok(bodyText.includes('Venue News') || bodyText.includes('News'), 'Venue News nav must be visible.');
    assert.ok(bodyText.includes('Reports'), 'Reports nav must be visible.');
    assert.ok(!bodyText.includes('Bookings'), 'Bookings nav must not be visible for cmart_management.');
  });
});
