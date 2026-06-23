import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env, requireVendorCredentials } from '../config/env.js';
import { createDriver } from '../helpers/driver.js';
import { waitForTestId, waitForUrlContains } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Authentication', function () {
  let driver;

  before(async function () {
    driver = await createDriver();
    setActiveDriver(driver);
  });

  it('Vendor/community user can log in and reach dashboard', async function () {
    const { email, password } = requireVendorCredentials();

    await driver.get(`${env.baseUrl}/login`);

    const emailInput = await waitForTestId(driver, 'login-email');
    const passwordInput = await driver.findElement(By.css('[data-testid="login-password"]'));
    const submitButton = await driver.findElement(By.css('[data-testid="login-submit"]'));

    await emailInput.clear();
    await emailInput.sendKeys(email);
    await passwordInput.clear();
    await passwordInput.sendKeys(password);
    await submitButton.click();

    await waitForUrlContains(driver, '/dashboard');
    const dashboardRoot = await waitForTestId(driver, 'vendor-dashboard-root');

    assert.equal(await dashboardRoot.isDisplayed(), true);
  });
});
