import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { requireOrganizerCredentials } from '../config/env.js';
import { loginAsOrganizer } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Organizer authentication', function () {
  this.timeout(120000);

  let driver;

  before(async function () {
    requireOrganizerCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  it('Organizer user can log in via management login and reach the management dashboard', async function () {
    await loginAsOrganizer(driver);
    const dashboardRoot = await waitForTestId(driver, 'management-dashboard-root');
    assert.equal(await dashboardRoot.isDisplayed(), true);

    const heroText = await driver.findElement(By.css('header')).getText();
    assert.ok(!/Manager/i.test(heroText), 'Hero must not contain legacy "Manager" label.');
    assert.ok(!/Staff Portal/i.test(heroText), 'Hero must not contain legacy "Staff Portal" label.');
  });
});
